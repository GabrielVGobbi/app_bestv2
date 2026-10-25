<?php

namespace Tests\Feature\Backup;

use App\Models\User;
use App\Modules\Backup\Jobs\ProcessDatabaseExport;
use App\Modules\Backup\Listeners\SyncBackupToHistory;
use App\Modules\Backup\Models\BackupHistory;
use App\Modules\Backup\Models\DatabaseExport;
use App\Modules\Backup\Providers\BackupServiceProvider;
use App\Modules\Permissions\Models\Role;
use App\Supports\DatabaseDumpOptions;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\SQLiteBuilder;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Databases\Sqlite;
use Tests\TestCase;
use Throwable;

class BackupContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
        $this->freezeTime();
        Storage::fake('local');
        Notification::fake();
    }

    public function test_backup_failure_before_disk_selection_is_preserved_without_accessing_storage(): void
    {
        $this->assertInstanceOf(BackupServiceProvider::class, $this->app->getProvider(BackupServiceProvider::class));
        $this->assertTrue(Event::hasListeners(BackupHasFailed::class));
        Storage::shouldReceive('disk')->never();

        app(SyncBackupToHistory::class)->handleFailure(new BackupHasFailed(
            new RuntimeException('BKP001 synthetic failure before selecting a destination'), diskName: null, backupName: null,
        ));

        $this->assertDatabaseCount('backup_histories', 1);
        $history = BackupHistory::query()->sole();
        $this->assertSame(BackupHistory::STATUS_FAILED, $history->status);
        $this->assertNull($history->disk);
        $this->assertNull($history->path);
        $this->assertNull($history->file_name);
        $this->assertNull($history->file_size);
        $this->assertSame('BKP001 synthetic failure before selecting a destination', $history->error_message);
        $this->assertSame(now()->toDateTimeString(), $history->backed_up_at->toDateTimeString());
        $this->assertIsolated();
    }

    public function test_sqlite_schema_requests_are_rejected_without_creating_or_dispatching_a_dump(): void
    {
        $actor = $this->developer();
        $this->actingAs($actor)->postJson('/api/v1/database-exports', ['export_type' => 'schema'])
            ->assertUnprocessable()->assertJsonValidationErrors('export_type');

        $this->assertDatabaseCount('database_exports', 0);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertIsolated();
    }

    public function test_successful_backup_history_still_points_to_the_newest_private_file(): void
    {
        $disk = Storage::disk('local');
        $older = 'bkp001-fixtures/older.zip';
        $newer = 'bkp001-fixtures/newer.zip';
        $disk->put($older, 'BKP001 older backup');
        $disk->put($newer, 'BKP001 newest private backup');
        $this->assertTrue(touch($disk->path($older), now()->subMinutes(2)->timestamp));
        $this->assertTrue(touch($disk->path($newer), now()->subMinute()->timestamp));
        $this->assertTrue(Event::hasListeners(BackupWasSuccessful::class));

        app(SyncBackupToHistory::class)->handleSuccess(new BackupWasSuccessful('local', 'bkp001-fixtures'));

        $history = BackupHistory::query()->sole();
        $this->assertSame('completed', $history->status);
        $this->assertSame('local', $history->disk);
        $this->assertSame($newer, $history->path);
        $this->assertSame('newer.zip', $history->file_name);
        $this->assertSame(strlen('BKP001 newest private backup'), $history->file_size);
        $this->assertNull($history->error_message);
        $this->assertSame('BKP001 older backup', $disk->get($older));
        $this->assertSame('BKP001 newest private backup', $disk->get($newer));
        $this->assertIsolated();
    }

    public function test_a_completed_history_with_no_disk_is_not_downloaded_from_the_default_disk(): void
    {
        $actor = $this->developer();
        $history = $this->history(['disk' => null, 'status' => 'completed', 'path' => 'bkp001-fixtures/private.zip']);
        $before = $history->fresh()->getRawOriginal();
        Storage::shouldReceive('disk')->never();

        $this->actingAs($actor)->get(route('admin.dev.backups.download', $history->uuid))->assertNotFound();

        $this->assertSame($before, $history->fresh()->getRawOriginal());
        $this->assertIsolated();
    }

    public function test_nullable_disk_migration_preserves_existing_histories_when_applied_and_reversed(): void
    {
        $migration = $this->nullableDiskMigration();
        $this->assertTrue(method_exists($migration, 'down'));
        $this->assertTrue(method_exists($migration, 'up'));
        $migration->down();
        $this->assertDiskNullable(false);
        $this->history(['status' => 'completed', 'disk' => 'local', 'path' => 'bkp001-fixtures/legacy.zip']);
        $this->history(['status' => 'failed', 'disk' => 'local', 'path' => null, 'error_message' => 'BKP001 historical failure']);
        $before = $this->historyRows();

        $migration->up();

        $this->assertDiskNullable(true);
        $this->assertSame($before, $this->historyRows());
        $migration->down();
        $this->assertDiskNullable(false);
        $this->assertSame($before, $this->historyRows());
        $migration->up();
        $this->assertDiskNullable(true);
        $this->assertSame($before, $this->historyRows());
        $this->assertIsolated();
    }

    public function test_nullable_disk_rollback_refuses_null_histories_without_rewriting_or_discarding_them(): void
    {
        $this->history(['status' => 'completed', 'disk' => 'local', 'path' => 'bkp001-fixtures/existing.zip']);
        $this->history(['status' => 'failed', 'disk' => null, 'path' => null, 'error_message' => 'BKP001 no destination selected']);
        $before = $this->historyRows();
        $failure = null;
        $migration = $this->nullableDiskMigration();
        $this->assertTrue(method_exists($migration, 'down'));
        try {
            $migration->down();
        } catch (Throwable $exception) {
            $failure = $exception;
        }

        $this->assertInstanceOf(RuntimeException::class, $failure);
        $this->assertDiskNullable(true);
        $this->assertSame($before, $this->historyRows());
        $this->assertDatabaseCount('backup_histories', 2);
        $this->assertIsolated();
    }

    public function test_sqlite_data_requests_are_rejected_without_creating_or_dispatching_a_dump(): void
    {
        $this->actingAs($this->developer())->postJson('/api/v1/database-exports', ['export_type' => 'data'])
            ->assertUnprocessable()->assertJsonValidationErrors('export_type');

        $this->assertDatabaseCount('database_exports', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertIsolated();
    }

    public function test_sqlite_full_remains_available_with_an_exact_safe_table_selection(): void
    {
        $actor = $this->developer();
        $this->actingAs($actor)->postJson('/api/v1/database-exports', ['export_type' => 'full', 'tables' => ['users']])
            ->assertCreated()->assertJsonPath('data.export_type', 'full');

        $export = DatabaseExport::query()->sole();
        $this->assertSame($actor->id, $export->user_id);
        $this->assertSame('pending', $export->status);
        $this->assertSame(['users'], $export->tables);
        Queue::assertPushed(ProcessDatabaseExport::class, 1);
        $this->assertSame([], Storage::disk('local')->allFiles());
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    #[DataProvider('unsafeSqliteSelections')]
    public function test_sqlite_table_selection_rejects_like_patterns_and_non_literal_identifiers(string $table): void
    {
        $this->actingAs($this->developer())->postJson('/api/v1/database-exports', ['export_type' => 'full', 'tables' => [$table]])
            ->assertUnprocessable()->assertJsonValidationErrors('tables.0');

        $this->assertDatabaseCount('database_exports', 0);
        $this->assertIsolated();
    }

    /** @return array<string, array{string}> */
    public static function unsafeSqliteSelections(): array
    {
        return ['existing underscore name' => ['backup_histories'], 'percent wildcard' => ['users%'], 'underscore wildcard' => ['user_'],
            'quoted name' => ['"users"'], 'statement fragment' => ['users; DROP TABLE users'], 'unknown literal' => ['missingtable']];
    }

    public function test_sqlite_connection_alias_uses_its_actual_driver_for_modes_and_table_catalogue(): void
    {
        $actor = $this->developer();
        $default = DB::getDefaultConnection();
        $connection = DB::connection();
        config(['database.connections.bkp001_alias' => config('database.connections.sqlite')]);
        DB::extend('bkp001_alias', fn (): Connection => $connection);
        DB::setDefaultConnection('bkp001_alias');
        try {
            $this->assertSame('sqlite', DB::connection()->getDriverName());
            $this->actingAs($actor)->getJson('/api/v1/database-exports/tables')->assertOk()
                ->assertJsonFragment(['name' => 'users'])->assertJsonFragment(['name' => 'backup_histories']);
            $this->postJson('/api/v1/database-exports', ['export_type' => 'schema'])->assertUnprocessable();
            $this->postJson('/api/v1/database-exports', ['export_type' => 'full', 'tables' => ['users']])->assertCreated();
            $this->assertDatabaseCount('database_exports', 1);
            Queue::assertPushed(ProcessDatabaseExport::class, 1);
        } finally {
            DB::setDefaultConnection($default);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        Mail::assertNothingOutgoing();
        Http::assertNothingSent();
    }

    #[DataProvider('supportedDriverModes')]
    public function test_supported_dump_modes_are_explicit_for_each_driver(string $driver, array $expected): void
    {
        $this->assertEqualsCanonicalizing($expected, DatabaseDumpOptions::supportedTypes($driver));
        $this->assertIsolated();
    }

    /** @return array<string, array{string, list<string>}> */
    public static function supportedDriverModes(): array
    {
        return ['mysql' => ['mysql', ['schema', 'data', 'full']], 'mariadb' => ['mariadb', ['schema', 'data', 'full']],
            'postgresql' => ['pgsql', ['schema', 'data', 'full']], 'sqlite' => ['sqlite', ['full']], 'unsupported' => ['sqlsrv', []],
            'connection name is not a driver' => ['bkp001_alias', []]];
    }

    #[DataProvider('sqlServerFamilies')]
    public function test_non_sqlite_table_filters_allow_literal_underscores_and_reject_patterns_or_sql_fragments(string $driver): void
    {
        $this->assertSame(['safe_table', 'users'], DatabaseDumpOptions::validateTables($driver, ['safe_table', 'users', 'safe_table']));
        foreach (['users%', 'table name', 'public.users', '"users"', 'users; DROP TABLE users'] as $table) {
            $failure = null;
            try {
                DatabaseDumpOptions::validateTables($driver, [$table]);
            } catch (RuntimeException $exception) {
                $failure = $exception;
            }
            $this->assertInstanceOf(RuntimeException::class, $failure, 'Only literal identifiers may reach the dumper table flags.');
        }
        $this->assertIsolated();
    }

    /** @return array<string, array{string}> */
    public static function sqlServerFamilies(): array
    {
        return ['mysql literals' => ['mysql'], 'mariadb literals' => ['mariadb'], 'postgresql literals' => ['pgsql']];
    }

    public function test_an_unsupported_actual_driver_is_rejected_before_schema_queries_or_dispatch(): void
    {
        $actor = $this->developer();
        $default = DB::getDefaultConnection();
        $original = DB::connection();
        $connection = new class($original->getPdo(), ':memory:', '', ['driver' => 'sqlite']) extends SQLiteConnection
        {
            public function getDriverName(): string
            {
                return 'sqlsrv';
            }

            public function getSchemaBuilder(): SQLiteBuilder
            {
                throw new RuntimeException('BKP001 unsupported driver must stop before schema enumeration.');
            }
        };
        config(['database.connections.bkp001_unsupported' => config('database.connections.sqlite')]);
        DB::extend('bkp001_unsupported', fn (): Connection => $connection);
        DB::setDefaultConnection('bkp001_unsupported');
        try {
            $this->actingAs($actor)->postJson('/api/v1/database-exports', ['export_type' => 'full'])->assertUnprocessable()
                ->assertJsonValidationErrors('export_type');
        } finally {
            DB::setDefaultConnection($default);
        }
        $this->assertDatabaseCount('database_exports', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertIsolated();
    }

    #[DataProvider('unsupportedSqliteJobs')]
    public function test_direct_sqlite_partial_jobs_fail_closed_before_dumper_publication_or_notice(string $mode): void
    {
        $export = DatabaseExport::query()->create(['user_id' => $this->developer()->id, 'export_type' => $mode, 'status' => 'pending', 'tables' => null]);
        $job = new BackupContractGuardJob($export);
        try {
            $job->handle();
        } catch (RuntimeException) {
            // A rejected direct payload may propagate its validation failure to the queue boundary.
        }

        $this->assertFalse($job->enteredDumper);
        $this->assertSame('failed', $export->fresh()->status);
        $this->assertNull($export->fresh()->file_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('app_notifications', 0);
        $this->assertIsolated();
    }

    /** @return array<string, array{string}> */
    public static function unsupportedSqliteJobs(): array
    {
        return ['schema cannot silently contain data' => ['schema'], 'data cannot silently contain schema' => ['data']];
    }

    public function test_postgresql_filters_accept_lowercase_identifiers_and_refuse_case_folding_ambiguity(): void
    {
        $this->assertSame(['safe_table', '_internal', 'users2'], DatabaseDumpOptions::validateTables('pgsql', ['safe_table', '_internal', 'users2']));
        foreach (['MixedCase', 'mixedCase', 'UPPER_TABLE'] as $table) {
            $failure = null;
            try {
                DatabaseDumpOptions::validateTables('pgsql', [$table]);
            } catch (RuntimeException $exception) {
                $failure = $exception;
            }
            $this->assertInstanceOf(RuntimeException::class, $failure, 'PostgreSQL filters must not silently fold a requested identifier.');
        }
        $this->assertIsolated();
    }

    #[DataProvider('postgresqlTableFlags')]
    public function test_postgresql_dumper_scopes_literal_table_flags_to_public(array $tables, array $excluded, string $flag): void
    {
        $export = new DatabaseExport(['export_type' => 'full', 'tables' => $tables]);
        $dumper = (new BackupContractDumperProbe($export))->makeDumper('pgsql', [
            'database' => 'bkp001_inert_database', 'username' => 'bkp001_inert_user', 'dump' => ['exclude_tables' => $excluded],
        ], $export);

        $this->assertInstanceOf(PostgreSql::class, $dumper);
        $command = $dumper->getDumpCommand(Storage::disk('local')->path('bkp001-fixtures/not-executed.sql'));
        $this->assertStringContainsString($flag.' public.safe_table', $command);
        $this->assertStringNotContainsString($flag.' safe_table', $command);
        if ($tables !== []) {
            $this->assertStringContainsString('--strict-names', $command);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertIsolated();
    }

    /** @return array<string, array{list<string>, list<string>, string}> */
    public static function postgresqlTableFlags(): array
    {
        return ['explicit include' => [['safe_table'], [], '-t'], 'configured exclude' => [[], ['safe_table'], '-T']];
    }

    #[DataProvider('unsafeEffectiveSqliteSelections')]
    public function test_sqlite_exclusions_reject_an_unsafe_or_empty_effective_selection(array $sourceTables, array $excluded): void
    {
        $source = $this->sqliteSource($sourceTables);
        $before = hash_file('sha256', $source);
        $connection = DB::connection();
        $export = new DatabaseExport(['export_type' => 'full', 'tables' => null]);
        $failure = null;
        try {
            (new BackupContractDumperProbe($export))->makeDumper('sqlite', [
                'database' => $source, 'dump' => ['exclude_tables' => $excluded],
            ], $export);
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }

        $this->assertInstanceOf(RuntimeException::class, $failure, 'Validate every remaining table before the SDK can build an unfiltered or unsafe .dump command.');
        $this->assertSame($connection, DB::connection());
        $this->assertSame($before, hash_file('sha256', $source));
        $this->assertSame(['bkp001-fixtures/source.sqlite'], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('database_exports', 0);
        $this->assertIsolated();
    }

    /** @return array<string, array{list<string>, list<string>}> */
    public static function unsafeEffectiveSqliteSelections(): array
    {
        return [
            'remaining underscore is a LIKE wildcard' => [['skip', 'unsafe_name'], ['skip']],
            'remaining shell fragment is not a literal' => [['skip', 'unsafe;echo forbidden'], ['skip']],
            'excluding every table must not dump everything' => [['skip'], ['skip']],
            'case variant is not an exact exclusion' => [['keep', 'skip'], ['Skip']],
            'unknown exclusion is not silently ignored' => [['keep', 'skip'], ['missing']],
        ];
    }

    public function test_sqlite_exclusions_become_an_explicit_nonempty_literal_include_set(): void
    {
        $source = $this->sqliteSource(['keep', 'skip']);
        $connection = DB::connection();
        $export = new DatabaseExport(['export_type' => 'full', 'tables' => null]);
        $dumper = (new BackupContractDumperProbe($export))->makeDumper('sqlite', [
            'database' => $source, 'dump' => ['exclude_tables' => ['skip']],
        ], $export);

        $this->assertInstanceOf(Sqlite::class, $dumper);
        $database = new \SQLite3($source);
        try {
            $this->assertTrue($database->exec('CREATE TABLE later (id INTEGER PRIMARY KEY)'));
        } finally {
            $database->close();
        }
        $before = hash_file('sha256', $source);
        $command = $dumper->getDumpCommand(Storage::disk('local')->path('bkp001-fixtures/not-executed.sql'));
        $this->assertStringContainsString('.dump keep', $command);
        $this->assertStringNotContainsString('.dump keep skip', $command);
        $this->assertStringNotContainsString('.dump skip', $command);
        $this->assertStringNotContainsString('later', $command, 'The SDK must receive the resolved include set instead of re-enumerating exclusions.');
        $this->assertSame($connection, DB::connection());
        $this->assertSame($before, hash_file('sha256', $source));
        $this->assertSame(['bkp001-fixtures/source.sqlite'], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('database_exports', 0);
        $this->assertIsolated();
    }

    public function test_a_direct_job_refuses_a_selected_table_removed_since_the_request_before_entering_the_dumper(): void
    {
        Schema::create('bkp001probe', function (Blueprint $table): void {
            $table->id();
        });
        $export = DatabaseExport::query()->create([
            'user_id' => $this->developer()->id, 'export_type' => 'full', 'status' => 'pending', 'tables' => ['bkp001probe'],
        ]);
        Schema::drop('bkp001probe');
        $this->assertFalse(Schema::hasTable('bkp001probe'));
        $job = new BackupContractGuardJob($export);
        try {
            $job->handle();
        } catch (RuntimeException) {
            // A stale selection may propagate its validation failure to the queue boundary.
        }

        $this->assertFalse($job->enteredDumper);
        $this->assertSame('failed', $export->fresh()->status);
        $this->assertNull($export->fresh()->file_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('app_notifications', 0);
        $this->assertIsolated();
    }

    #[DataProvider('unavailableSqliteSources')]
    public function test_sqlite_dumper_refuses_a_nonpersistent_or_missing_source_without_creating_it(string $kind): void
    {
        $path = $kind === 'memory' ? ':memory:' : Storage::disk('local')->path('missing-source.sqlite');
        $export = new DatabaseExport(['export_type' => 'full', 'tables' => null]);
        try {
            (new BackupContractDumperProbe($export))->makeDumper('sqlite', ['database' => $path], $export);
            $this->fail('SQLite requires an existing persistent source before constructing a dumper.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('existing persistent', $exception->getMessage());
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertIsolated();
    }

    /** @return array<string, array{string}> */
    public static function unavailableSqliteSources(): array
    {
        return ['memory is not an on-disk source' => ['memory'], 'missing source must not be created by the SDK' => ['missing']];
    }

    /** @param list<string> $tables */
    private function sqliteSource(array $tables): string
    {
        $disk = Storage::disk('local');
        $this->assertTrue($disk->put('bkp001-fixtures/source.sqlite', ''));
        $root = realpath($disk->path(''));
        $source = realpath($disk->path('bkp001-fixtures/source.sqlite'));
        $this->assertIsString($root);
        $this->assertIsString($source);
        $this->assertStringStartsWith(str_replace('\\', '/', $root).'/', str_replace('\\', '/', $source));
        $this->assertFalse(is_link($source));
        $database = new \SQLite3($source);
        $database->enableExceptions(true);
        try {
            foreach ($tables as $table) {
                $identifier = '"'.str_replace('"', '""', $table).'"';
                $this->assertTrue($database->exec('CREATE TABLE '.$identifier.' (id INTEGER PRIMARY KEY, value TEXT NOT NULL)'));
                $this->assertTrue($database->exec('INSERT INTO '.$identifier." (value) VALUES ('BKP001 owned fixture')"));
            }
        } finally {
            $database->close();
        }

        return $source;
    }

    /** @param array<string, mixed> $attributes */
    private function history(array $attributes = []): BackupHistory
    {
        return BackupHistory::query()->create(['disk' => 'local', 'status' => 'completed', 'path' => 'bkp001-fixtures/backup.zip',
            'file_name' => 'backup.zip', 'file_size' => 17, 'backed_up_at' => now(), ...$attributes]);
    }

    private function nullableDiskMigration(): Migration
    {
        $root = dirname(__DIR__, 3);
        $name = '2026_10_18_223539_make_backup_history_disk_nullable.php';
        $paths = array_values(array_filter([$root.'/app/Modules/Backup/Database/Migrations/'.$name, $root.'/database/migrations/'.$name], is_file(...)));
        $this->assertCount(1, $paths, 'The additive nullable disk migration must have one canonical location.');

        return require $paths[0];
    }

    private function assertDiskNullable(bool $expected): void
    {
        $column = collect(Schema::getColumns('backup_histories'))->firstWhere('name', 'disk');
        $this->assertNotNull($column);
        $this->assertSame($expected, $column['nullable']);
    }

    /** @return list<array<string, mixed>> */
    private function historyRows(): array
    {
        return BackupHistory::query()->orderBy('id')->get()->map(function (BackupHistory $history): array {
            $attributes = $history->getRawOriginal();
            ksort($attributes);

            return $attributes;
        })->all();
    }

    private function developer(): User
    {
        $actor = User::factory()->create(['must_change_password' => false]);
        $actor->roles()->attach(Role::firstOrCreate(['slug' => 'developer'], ['name' => 'Developer']));

        return $actor->load('roles');
    }

    private function assertIsolated(): void
    {
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }
}

class BackupContractGuardJob extends ProcessDatabaseExport
{
    public bool $enteredDumper = false;

    protected function buildDumper(string $driver, array $config, DatabaseExport $export): MySql|PostgreSql|Sqlite
    {
        $this->enteredDumper = true;
        throw new RuntimeException('BKP001 forbidden dumper boundary reached by an unsupported direct payload.');
    }
}

class BackupContractDumperProbe extends ProcessDatabaseExport
{
    /** @param array<string, mixed> $config */
    public function makeDumper(string $driver, array $config, DatabaseExport $export): MySql|PostgreSql|Sqlite
    {
        return parent::buildDumper($driver, $config, $export);
    }
}
