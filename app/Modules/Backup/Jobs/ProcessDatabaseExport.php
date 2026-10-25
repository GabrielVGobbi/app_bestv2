<?php

declare(strict_types=1);

namespace App\Modules\Backup\Jobs;

use App\Modules\Backup\Models\DatabaseExport;
use App\Modules\Notifications\Actions\CreateNotificationAction;
use App\Modules\Notifications\DTOs\CreateNotificationDTO;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Models\AppNotification;
use App\Supports\DatabaseDumpOptions;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Databases\Sqlite;
use Throwable;

class ProcessDatabaseExport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = DatabaseExport::PROCESSING_TIMEOUT;

    public int $tries = 1;

    public int $backoff = 0;

    public int $uniqueFor = 1020;

    public bool $failOnTimeout = true;

    private ?string $attemptToken = null;

    public function __construct(public readonly DatabaseExport $export)
    {
        $this->attemptToken = (string) Str::uuid();
    }

    public function uniqueId(): string
    {
        return $this->export->uuid;
    }

    public function handle(): void
    {
        $export = $this->export->fresh();

        if ($export === null || $this->rejectLegacyPayload($export)) {
            return;
        }

        $storagePath = $export->candidatePath($this->attemptToken);

        if (! $export->claimAttempt($storagePath)) {
            return;
        }

        $completed = false;

        try {
            $connection = DB::connection();
            $config = $connection->getConfig();
            $driver = $connection->getDriverName();
            DatabaseDumpOptions::assertSupportedType($driver, $export->export_type);
            $selectedTables = DatabaseDumpOptions::validateTables($driver, $export->tables ?? []);
            if ($selectedTables !== [] && array_diff($selectedTables, DatabaseDumpOptions::tables($connection)) !== []) {
                throw new RuntimeException('Database dump selection no longer exists in the resolved connection.');
            }

            $absolute = $export->resolveArtifactPath($storagePath);

            if ($absolute === null) {
                throw new RuntimeException('Database dump requires a canonical private local destination.');
            }

            File::ensureDirectoryExists(dirname($absolute));

            $temporary = $export->resolveTemporaryPath($storagePath);

            if ($temporary === null || file_exists($temporary) || is_link($temporary)) {
                throw new RuntimeException('Database dump staging destination is unavailable.');
            }

            $disk = config('database-exports.disk', 'local');
            $dumper = $this->buildDumper($driver, $config, $export);
            $dumper->dumpToFile($temporary);
            $this->assertActiveAttempt($export, $storagePath);

            clearstatcache(true, $temporary);
            $temporarySize = is_file($temporary) ? filesize($temporary) : false;

            if ($export->resolveTemporaryPath($storagePath) !== $temporary || $temporarySize === false || $temporarySize <= 0) {
                throw new RuntimeException('Database dump did not produce a complete staging file.');
            }

            $stream = fopen($temporary, 'rb');

            if (! is_resource($stream)) {
                throw new RuntimeException('Database dump staging file could not be opened.');
            }

            try {
                if (! Storage::disk($disk)->put($storagePath, $stream)) {
                    throw new RuntimeException('Database dump publication was refused.');
                }
            } finally {
                fclose($stream);
            }

            $fileSize = Storage::disk($disk)->size($storagePath);
            $fileName = "database-export-{$export->uuid}.sql";

            if ($export->getConnection() !== (new AppNotification)->getConnection()) {
                throw new RuntimeException('Database export and notification must use the same transactional connection.');
            }

            if ($fileSize !== $temporarySize || ! $export->completeAttempt($storagePath, $fileName, $fileSize, function (DatabaseExport $current) use ($fileName): void {
                app(CreateNotificationAction::class)->handle(
                    CreateNotificationDTO::system(
                        recipientId: $current->user_id,
                        title: 'Dump de banco pronto!',
                        body: "O arquivo {$fileName} está pronto para download.",
                        category: NotificationCategory::EXPORT,
                        link: route('admin.dev.backups.database-exports.download', $current->uuid),
                    ),
                    afterCommit: true,
                );
            })) {
                throw new RuntimeException('Database dump producer no longer owns an active claim.');
            }

            $completed = true;

        } catch (Throwable $exception) {
            if (! $completed) {
                $export->failAttempt($storagePath);
            }

            throw $exception;
        } finally {
            if ($completed) {
                $export->cleanupTemporary($storagePath);
            } else {
                $export->cleanupAttempt($storagePath);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $export = $this->export->fresh();

        if ($export === null || $this->rejectLegacyPayload($export)) {
            return;
        }

        $path = $export->candidatePath($this->attemptToken);

        if ($export->failAttempt($path)) {
            report($exception);
            $export->cleanupArtifacts();
        }
    }

    /** @phpstan-assert-if-false string $this->attemptToken */
    private function rejectLegacyPayload(DatabaseExport $export): bool
    {
        if (isset($this->attemptToken)) {
            return false;
        }

        $export->newQuery()->whereKey($export->getKey())->where('uuid', $export->uuid)
            ->where('status', DatabaseExport::STATUS_PENDING)->whereNull('file_path')
            ->update(['status' => DatabaseExport::STATUS_FAILED, 'error_message' => DatabaseExport::FAILURE_MESSAGE]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function buildDumper(string $driver, array $config, DatabaseExport $export): MySql|PostgreSql|Sqlite
    {
        DatabaseDumpOptions::assertSupportedType($driver, $export->export_type);
        $tables = DatabaseDumpOptions::validateTables($driver, $export->tables ?? []);
        $excluded = $tables === [] ? DatabaseDumpOptions::validateTables($driver, $config['dump']['exclude_tables'] ?? []) : [];
        if ($driver === 'sqlite' && (! is_string($config['database'] ?? null) || ! is_file($config['database']))) {
            throw new RuntimeException('SQLite dump requires an existing persistent database file.');
        }

        $dumper = match ($driver) {
            'mysql', 'mariadb' => MySql::create()
                ->setHost($config['host'] ?? '127.0.0.1')
                ->setPort((int) ($config['port'] ?? 3306))
                ->setDbName($config['database'])
                ->setUserName($config['username'])
                ->setPassword($config['password'] ?? ''),
            'pgsql' => PostgreSql::create()
                ->setHost($config['host'] ?? '127.0.0.1')
                ->setPort((int) ($config['port'] ?? 5432))
                ->setDbName($config['database'])
                ->setUserName($config['username'])
                ->setPassword($config['password'] ?? ''),
            'sqlite' => Sqlite::create()
                ->setDbName($config['database']),
            default => throw new RuntimeException("Unsupported database driver: {$driver}"),
        };

        // Export type flags
        if ($driver !== 'sqlite') {
            if ($export->export_type === DatabaseExport::TYPE_SCHEMA) {
                if ($driver === 'mysql' || $driver === 'mariadb') {
                    $dumper->doNotDumpData();
                } else {
                    $dumper->addExtraOption('--schema-only');
                }
            } elseif ($export->export_type === DatabaseExport::TYPE_DATA) {
                if ($driver === 'mysql' || $driver === 'mariadb') {
                    $dumper->addExtraOption('--no-create-info');
                } else {
                    $dumper->addExtraOption('--data-only');
                }
            }
        }

        if ($dumper instanceof Sqlite && ($tables !== [] || $excluded !== [])) {
            $available = $dumper->getDbTables();
            if (array_diff($excluded, $available) !== []) {
                throw new RuntimeException('SQLite dump exclusions must match existing table names exactly.');
            }
            if ($tables === []) {
                $tables = DatabaseDumpOptions::validateTables($driver, array_values(array_diff($available, $excluded)));
                $excluded = [];
            }
            if ($tables === [] || array_diff($tables, $available) !== []) {
                throw new RuntimeException('SQLite dump selection is missing or would fall back to an unfiltered dump.');
            }
        }

        if ($driver === 'pgsql') {
            $tables = array_map(static fn (string $table): string => 'public.'.$table, $tables);
            $excluded = array_map(static fn (string $table): string => 'public.'.$table, $excluded);
            if ($tables !== []) {
                $dumper->addExtraOption('--strict-names');
            }
        }

        if ($tables !== []) {
            $dumper->includeTables($tables);
        } elseif ($excluded !== []) {
            $dumper->excludeTables($excluded);
        }

        return $dumper->setTimeout(840);
    }

    private function assertActiveAttempt(DatabaseExport $export, string $path): void
    {
        $current = $export->fresh();

        if ($current === null || $current->status !== DatabaseExport::STATUS_PROCESSING || $current->file_path !== $path
            || $current->started_at === null || $current->started_at->lte(now()->subSeconds(DatabaseExport::PROCESSING_TIMEOUT))) {
            throw new RuntimeException('Database dump attempt is no longer current.');
        }
    }
}
