<?php

declare(strict_types=1);

namespace App\Modules\Backup\Jobs;

use App\Modules\Backup\Models\DatabaseExport;
use App\Modules\Notifications\Actions\CreateNotificationAction;
use App\Modules\Notifications\DTOs\CreateNotificationDTO;
use App\Modules\Notifications\Enums\NotificationCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Databases\Sqlite;
use Throwable;

class ProcessDatabaseExport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly DatabaseExport $export) {}

    public function uniqueId(): string
    {
        return $this->export->uuid;
    }

    public function handle(): void
    {
        $export = $this->export->fresh();

        if (! $export || ! in_array($export->status, [DatabaseExport::STATUS_PENDING, DatabaseExport::STATUS_PROCESSING], true)) {
            return;
        }

        $export->markAsProcessing();

        $config = config('database.connections.'.config('database.default'));
        $driver = $config['driver'] ?? 'mysql';

        $disk = config('database-exports.disk', 'local');
        $directory = config('database-exports.directory', 'database-exports');
        $extension = 'sql';
        $storagePath = "{$directory}/{$export->uuid}.{$extension}";
        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.$export->uuid.'.'.$extension;

        try {
            $dumper = $this->buildDumper($driver, $config, $export);

            $dumper->dumpToFile($tempPath);

            $stream = fopen($tempPath, 'r');
            Storage::disk($disk)->put($storagePath, $stream);
            fclose($stream);

            $fileSize = Storage::disk($disk)->size($storagePath);
            $fileName = "database-export-{$export->uuid}.sql";

            $export->markAsCompleted($storagePath, $fileName, $fileSize);

            app(CreateNotificationAction::class)->handle(
                CreateNotificationDTO::system(
                    recipientId: $export->user_id,
                    title: 'Dump de banco pronto!',
                    body: "O arquivo {$fileName} está pronto para download.",
                    category: NotificationCategory::EXPORT,
                    link: route('admin.dev.backups.database-exports.download', $export->uuid),
                )
            );
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->export->markAsFailed($exception->getMessage());
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildDumper(string $driver, array $config, DatabaseExport $export): MySql|PostgreSql|Sqlite
    {
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
            default => throw new \RuntimeException("Unsupported database driver: {$driver}"),
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

        if (! empty($export->tables) && method_exists($dumper, 'includeTables')) {
            $dumper->includeTables($export->tables);
        } else {
            $excluded = $config['dump']['exclude_tables'] ?? [];

            if (! empty($excluded)) {
                $dumper->excludeTables($excluded);
            }
        }

        return $dumper;
    }
}
