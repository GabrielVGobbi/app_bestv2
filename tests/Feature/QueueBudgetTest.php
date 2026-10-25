<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\AppInitCommand;
use App\Jobs\ProcessExport;
use App\Models\Export;
use App\Models\User;
use App\Modules\Backup\Jobs\ProcessDatabaseExport;
use App\Modules\Backup\Models\DatabaseExport;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Permissions\Models\Role;
use App\Modules\Uploads\Jobs\GenerateZipJob;
use App\Modules\Uploads\Models\ZipRequest;
use Closure;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Spatie\DbDumper\Databases\Sqlite;
use stdClass;
use Tests\TestCase;
use Throwable;

class QueueBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        config(['database-exports.disk' => 'local', 'database-exports.directory' => 'database-exports']);
    }

    #[DataProvider('reservationOverrides')]
    public function test_reservation_budgets_keep_a_minimum_above_every_worker_budget(?string $override, int $expected): void
    {
        $names = ['DB_QUEUE_RETRY_AFTER', 'REDIS_QUEUE_RETRY_AFTER', 'BEANSTALKD_QUEUE_RETRY_AFTER'];
        $saved = [];

        foreach ($names as $name) {
            $saved[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];

            if ($override === null) {
                unset($_ENV[$name], $_SERVER[$name]);
                putenv($name);
            } else {
                $_ENV[$name] = $_SERVER[$name] = $override;
                putenv($name.'='.$override);
            }
        }

        try {
            $configuration = require base_path('config/queue.php');

            foreach (['database', 'redis', 'beanstalkd'] as $connection) {
                $this->assertSame($expected, $configuration['connections'][$connection]['retry_after']);
                $this->assertGreaterThan(960, $configuration['connections'][$connection]['retry_after']);
            }
        } finally {
            foreach ($saved as $name => [$environment, $server, $process]) {
                if ($environment === null) {
                    unset($_ENV[$name]);
                } else {
                    $_ENV[$name] = $environment;
                }

                if ($server === null) {
                    unset($_SERVER[$name]);
                } else {
                    $_SERVER[$name] = $server;
                }

                putenv($process === false ? $name : $name.'='.$process);
            }
        }
    }

    /** @return array<string, array{?string, int}> */
    public static function reservationOverrides(): array
    {
        return ['default' => [null, 1020], 'short override clamped' => ['10', 1020], 'larger override retained' => ['2040', 2040]];
    }

    public function test_dev_and_generated_template_use_the_same_listener_budget(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        $template = (new ReflectionMethod(AppInitCommand::class, 'privateConfigTemplate'))->invoke(new AppInitCommand);
        $command = 'php artisan queue:listen --timeout=960 --tries=3 --backoff=30 --sleep=1';

        $this->assertStringContainsString($command, implode("\n", $composer['scripts']['dev']));
        $this->assertStringContainsString('queue='.$command, $template);
    }

    public function test_jobs_publish_their_category_budgets_without_changing_mail_retry_semantics(): void
    {
        $owner = User::factory()->create();
        $export = new ProcessExport(Export::factory()->create(['user_id' => $owner->id]));
        $dump = new ProcessDatabaseExport($this->export());
        $mail = new ProcessEmailJob(EmailLog::factory()->create());
        $zip = new GenerateZipJob(ZipRequest::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => $owner->id, 'label' => 'OPS001 inert ZIP',
            'file_ids' => [], 'file_count' => 0, 'status' => 'pending',
        ]));

        $this->assertSame(600, $export->timeout);
        $this->assertSame(900, $dump->timeout);
        $this->assertSame(1, $dump->tries);
        $this->assertSame(0, $dump->backoff);
        $this->assertSame(1020, $dump->uniqueFor);
        $this->assertSame(60, $mail->timeout);
        $this->assertSame(3, $mail->tries);
        $this->assertSame([30, 120, 300], $mail->backoff);
        $this->assertSame(60, $zip->timeout);
        $this->assertSame(1, $zip->tries);
    }

    public function test_real_dumper_configuration_has_a_budget_inside_the_job_budget(): void
    {
        $path = Storage::disk('local')->path('budget-source.sqlite');
        $source = new \SQLite3($path);
        $this->assertTrue($source->exec('CREATE TABLE bkpbudget (id INTEGER PRIMARY KEY)'));
        $source->close();
        $before = hash_file('sha256', $path);
        $job = new ProcessDatabaseExport($this->export());
        $dumper = (new ReflectionMethod($job, 'buildDumper'))->invoke($job, 'sqlite', ['driver' => 'sqlite', 'database' => $path], $this->export());

        $this->assertSame(840, (new ReflectionProperty($dumper, 'timeout'))->getValue($dumper));
        $this->assertSame($before, hash_file('sha256', $path));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    public function test_dump_materializes_an_owned_candidate_once_and_closes_its_stream(): void
    {
        $export = $this->export();
        $observed = new stdClass;
        $this->outputAdapter($observed);
        $dumper = new InertBudgetDumper;
        $dumper->beforeWrite = function () use ($export): void {
            $claimed = $export->fresh();
            $this->assertSame(DatabaseExport::STATUS_PROCESSING, $claimed->status);
            $this->assertOwnedCandidate($claimed);
        };

        (new InertDatabaseExportJob($export, $dumper))->handle();

        $ready = $export->fresh();
        $this->assertSame(DatabaseExport::STATUS_COMPLETED, $ready->status);
        $this->assertOwnedCandidate($ready);
        $this->assertSame(InertBudgetDumper::CONTENTS, Storage::disk('local')->get($ready->file_path));
        $this->assertSame(1, $dumper->calls);
        $this->assertFalse(is_resource($observed->stream));
        $this->assertFileDoesNotExist($dumper->temporaryPath);
    }

    #[DataProvider('claimedOrTerminalStates')]
    public function test_dump_redelivery_cannot_reopen_claimed_or_terminal_exports(string $status): void
    {
        $export = $this->export(['status' => $status, 'started_at' => now()]);
        $path = $this->candidate($export);
        $export->update(['file_path' => $path]);
        Storage::disk('local')->put($path, 'OPS001 terminal sentinel');
        $before = $export->fresh()->getRawOriginal();
        $dumper = new InertBudgetDumper;

        (new InertDatabaseExportJob($export, $dumper))->handle();

        $this->assertSame($before, $export->fresh()->getRawOriginal());
        $this->assertSame('OPS001 terminal sentinel', Storage::disk('local')->get($path));
        $this->assertSame(0, $dumper->calls);
    }

    /** @return array<string, array{string}> */
    public static function claimedOrTerminalStates(): array
    {
        return ['processing' => ['processing'], 'completed' => ['completed'], 'failed' => ['failed']];
    }

    public function test_second_delivery_during_dumping_cannot_enter_the_dumper(): void
    {
        $export = $this->export();
        $secondDumper = new InertBudgetDumper;
        $second = new InertDatabaseExportJob($export->fresh(), $secondDumper);
        $firstDumper = new InertBudgetDumper;
        $firstDumper->beforeWrite = fn () => $second->handle();

        (new InertDatabaseExportJob($export, $firstDumper))->handle();

        $this->assertSame(1, $firstDumper->calls);
        $this->assertSame(0, $secondDumper->calls);
        $this->assertSame(DatabaseExport::STATUS_COMPLETED, $export->fresh()->status);
        $this->assertCount(1, Storage::disk('local')->allFiles('database-exports'));
    }

    public function test_dumper_failure_is_generic_and_cleans_its_candidate_and_temporary_file(): void
    {
        $export = $this->export();
        $dumper = new InertBudgetDumper;
        $dumper->failure = new RuntimeException('OPS001 private diagnostic must not reach the public error.');

        $this->runExpectedFailure(new InertDatabaseExportJob($export, $dumper));

        $this->assertFailedAndClean($export);
        $this->assertFileDoesNotExist($dumper->temporaryPath);
    }

    #[DataProvider('outputFailures')]
    public function test_publication_failure_closes_the_stream_and_removes_only_its_candidate(string $failure): void
    {
        $export = $this->export();
        Storage::disk('local')->put('original-source.sql', 'OPS001 original sentinel');
        $observed = new stdClass;
        $this->outputAdapter($observed, $failure);
        $dumper = new InertBudgetDumper;

        $this->runExpectedFailure(new InertDatabaseExportJob($export, $dumper));

        $this->assertFailedAndClean($export);
        $this->assertFalse(is_resource($observed->stream));
        $this->assertSame('OPS001 original sentinel', Storage::disk('local')->get('original-source.sql'));
        $this->assertFileDoesNotExist($dumper->temporaryPath);
    }

    /** @return array<string, array{string}> */
    public static function outputFailures(): array
    {
        return ['put false' => ['put-false'], 'put exception' => ['put-throw'], 'put true with truncation' => ['put-truncated'], 'size exception' => ['size-throw']];
    }

    public function test_late_producer_cannot_publish_or_clean_a_different_attempt(): void
    {
        $export = $this->export();
        $otherPath = $this->candidate($export);
        Storage::disk('local')->put($otherPath, 'OPS001 different attempt sentinel');
        $dumper = new InertBudgetDumper;
        $dumper->beforeWrite = function () use ($export, $otherPath): void {
            DatabaseExport::query()->whereKey($export->id)->update([
                'status' => DatabaseExport::STATUS_FAILED, 'file_path' => $otherPath,
            ]);
        };

        $this->runExpectedFailure(new InertDatabaseExportJob($export, $dumper));

        $this->assertSame(DatabaseExport::STATUS_FAILED, $export->fresh()->status);
        $this->assertSame($otherPath, $export->fresh()->file_path);
        $this->assertSame('OPS001 different attempt sentinel', Storage::disk('local')->get($otherPath));
        $this->assertSame([$otherPath], Storage::disk('local')->allFiles('database-exports'));
    }

    #[DataProvider('callbackProtectedStates')]
    public function test_late_failure_callback_preserves_terminal_or_foreign_claim(string $status): void
    {
        $export = $this->export(['status' => $status, 'started_at' => now(), 'completed_at' => now()]);
        $path = $this->candidate($export);
        $export->update(['file_path' => $path]);
        Storage::disk('local')->put($path, 'OPS001 callback sentinel');
        $before = $export->fresh()->getRawOriginal();

        (new ProcessDatabaseExport($export))->failed(new RuntimeException('OPS001 delayed failure'));

        $this->assertSame($before, $export->fresh()->getRawOriginal());
        $this->assertSame('OPS001 callback sentinel', Storage::disk('local')->get($path));
    }

    /** @return array<string, array{string}> */
    public static function callbackProtectedStates(): array
    {
        return ['completed' => ['completed'], 'foreign processing claim' => ['processing']];
    }

    public function test_timeout_callback_during_its_own_claim_fails_without_publishing(): void
    {
        $export = $this->export();
        $dumper = new InertBudgetDumper;
        $job = new InertDatabaseExportJob($export, $dumper);
        $dumper->beforeWrite = fn () => $job->failed(new RuntimeException('OPS001 controlled timeout callback'));

        $this->runExpectedFailure($job);

        $this->assertFailedAndClean($export);
    }

    public function test_prune_reconciles_stale_claims_but_preserves_active_processing_with_old_expiry(): void
    {
        $stale = $this->export(['status' => 'processing', 'started_at' => now()->subSeconds(901)]);
        $stalePath = $this->candidate($stale);
        $stale->update(['file_path' => $stalePath]);
        Storage::disk('local')->put($stalePath, 'OPS001 abandoned candidate');
        Storage::disk('local')->put($stalePath.'.part', 'OPS001 interrupted staging candidate');
        $active = $this->export(['status' => 'processing', 'started_at' => now(), 'expires_at' => now()->subHour()]);
        $activePath = $this->candidate($active);
        $active->update(['file_path' => $activePath]);
        Storage::disk('local')->put($activePath, 'OPS001 active sentinel');
        $before = $active->fresh()->getRawOriginal();

        $this->artisan('database-exports:prune')->assertSuccessful();

        $this->assertFailedAndClean($stale);
        $this->assertSame($before, $active->fresh()->getRawOriginal());
        $this->assertSame('OPS001 active sentinel', Storage::disk('local')->get($activePath));
    }

    public function test_prune_fails_abandoned_pending_delivery_without_touching_fresh_pending(): void
    {
        $stale = $this->export();
        DatabaseExport::query()->whereKey($stale->id)->update(['updated_at' => now()->subSeconds(1021)]);
        $fresh = $this->export();
        $before = $fresh->fresh()->getRawOriginal();

        $this->artisan('database-exports:prune')->assertSuccessful();

        $this->assertFailedAndClean($stale);
        $this->assertSame($before, $fresh->fresh()->getRawOriginal());
        $dumper = new InertBudgetDumper;
        (new InertDatabaseExportJob($stale->fresh(), $dumper))->handle();
        $this->assertSame(0, $dumper->calls);
        $this->assertSame(DatabaseExport::STATUS_FAILED, $stale->fresh()->status);
    }

    public function test_abandoned_pending_dump_cannot_be_claimed_after_its_deadline(): void
    {
        $export = $this->export();
        DatabaseExport::query()->whereKey($export->id)->update(['updated_at' => now()->subSeconds(1021)]);
        $dumper = new InertBudgetDumper;

        (new InertDatabaseExportJob($export, $dumper))->handle();

        $this->assertSame(0, $dumper->calls);
        $this->assertFailedAndClean($export);
        $this->assertDatabaseCount('app_notifications', 0);
    }

    #[DataProvider('noticeFailures')]
    public function test_notice_persistence_failure_cannot_leave_a_completed_unannounced_dump(string $failure): void
    {
        $export = $this->export();
        $dispatcher = AppNotification::getEventDispatcher();
        $this->assertNotNull($dispatcher);
        AppNotification::setEventDispatcher(clone $dispatcher);

        try {
            AppNotification::creating(static function () use ($failure): bool {
                if ($failure === 'throw') {
                    throw new RuntimeException('OPS001 controlled notice persistence failure');
                }

                return false;
            });

            $this->runExpectedFailure(new InertDatabaseExportJob($export, new InertBudgetDumper));
        } finally {
            AppNotification::setEventDispatcher($dispatcher);
        }

        $this->assertFailedAndClean($export);
        $this->assertDatabaseCount('app_notifications', 0);
    }

    /** @return array<string, array{string}> */
    public static function noticeFailures(): array
    {
        return ['observer veto' => ['veto'], 'observer exception' => ['throw']];
    }

    public function test_dump_completion_fails_closed_when_notification_uses_a_different_connection(): void
    {
        $export = $this->export();
        $pdo = DB::connection('sqlite')->getPdo();
        config(['database.connections.ops001-dump-fixture' => config('database.connections.sqlite')]);
        DB::extend('ops001-dump-fixture', static fn (array $configuration, string $name): SQLiteConnection => new SQLiteConnection($pdo, ':memory:', '', array_replace($configuration, ['name' => $name])));
        $export->setConnection('ops001-dump-fixture');

        try {
            $this->assertNotSame($export->getConnection(), (new AppNotification)->getConnection());
            $failure = $this->runExpectedFailure(new InertDatabaseExportJob($export, new InertBudgetDumper));

            $this->assertNotNull($failure);
            $this->assertStringContainsString('same transactional connection', $failure->getMessage());

            $this->assertFailedAndClean($export);
            $this->assertDatabaseCount('app_notifications', 0);
        } finally {
            DB::purge('ops001-dump-fixture');
        }
    }

    #[DataProvider('cleanupFailures')]
    public function test_prune_keeps_identifiable_residue_and_reports_cleanup_failure(string $failure): void
    {
        $export = $this->export(['status' => 'completed', 'expires_at' => now()->subMinute()]);
        $path = $this->candidate($export);
        $export->update(['file_path' => $path]);
        Storage::disk('local')->put($path, 'OPS001 retained expired candidate');
        $observed = new stdClass;
        $base = $this->outputAdapter($observed, $failure);

        $this->artisan('database-exports:prune')->assertExitCode(1);

        $this->assertNotNull($export->fresh());
        $this->assertSame($path, $export->fresh()->file_path);
        $this->assertSame('OPS001 retained expired candidate', Storage::disk('local')->get($path));
        Storage::set('local', $base);

        $this->artisan('database-exports:prune')->assertSuccessful();

        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertNull($export->fresh());
    }

    /** @return array<string, array{string}> */
    public static function cleanupFailures(): array
    {
        return ['delete false' => ['delete-false'], 'delete exception' => ['delete-throw']];
    }

    public function test_prune_refuses_foreign_paths_and_preserves_original_files(): void
    {
        $export = $this->export(['status' => 'completed', 'expires_at' => now()->subMinute()]);
        $foreignPath = 'database-exports/'.Str::uuid().'/'.Str::uuid().'.sql';
        $export->update(['file_path' => $foreignPath]);
        Storage::disk('local')->put($foreignPath, 'OPS001 foreign sentinel');

        $this->artisan('database-exports:prune')->assertExitCode(1);

        $this->assertNotNull($export->fresh());
        $this->assertSame($foreignPath, $export->fresh()->file_path);
        $this->assertSame('OPS001 foreign sentinel', Storage::disk('local')->get($foreignPath));
    }

    public function test_recovery_requires_a_new_request_and_never_reopens_the_failed_export(): void
    {
        $owner = User::factory()->create();
        $role = Role::firstOrCreate(['slug' => 'developer'], ['name' => 'Developer']);
        $owner->roles()->attach($role);
        $failed = $this->export(['user_id' => $owner->id, 'status' => 'failed', 'error_message' => 'OPS001 previous failure']);
        $before = $failed->fresh()->getRawOriginal();

        $response = $this->actingAs($owner)->postJson('/api/v1/database-exports', ['export_type' => 'full']);

        $response->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->assertNotSame($failed->uuid, $response->json('data.uuid'));
        $this->assertSame($before, $failed->fresh()->getRawOriginal());
        Queue::assertPushed(ProcessDatabaseExport::class, 1);
    }

    #[DataProvider('legacyDumpStates')]
    public function test_legacy_dump_payload_without_an_attempt_token_is_reconciled_without_io(string $status, bool $callbackFirst, bool $hasPath): void
    {
        $export = $this->export(['status' => $status, 'started_at' => $status === DatabaseExport::STATUS_PROCESSING ? now() : null]);
        if ($hasPath) {
            $export->update(['file_path' => 'database-exports/'.$export->uuid.'.sql']);
        }
        $before = $export->fresh()->getRawOriginal();
        $payload = (new ProcessDatabaseExport($export))->__serialize();
        $key = "\0".ProcessDatabaseExport::class."\0attemptToken";
        $this->assertArrayHasKey($key, $payload);
        unset($payload[$key]);
        $legacy = (new \ReflectionClass(ProcessDatabaseExport::class))->newInstanceWithoutConstructor();
        $legacy->__unserialize($payload);
        $this->assertNull((new ReflectionProperty(ProcessDatabaseExport::class, 'attemptToken'))->getValue($legacy));
        config(['database.connections.sqlite.driver' => 'unsupported-OPS001-no-real-dumper']);
        Storage::shouldReceive('disk')->never();

        if ($callbackFirst) {
            $legacy->failed(new RuntimeException('OPS001 delayed legacy failure'));
            $legacy->handle();
        } else {
            $legacy->handle();
            $legacy->failed(new RuntimeException('OPS001 delayed legacy failure'));
        }

        if ($status === DatabaseExport::STATUS_PENDING && ! $hasPath) {
            $this->assertSame(DatabaseExport::STATUS_FAILED, $export->fresh()->status);
            $this->assertSame(DatabaseExport::FAILURE_MESSAGE, $export->fresh()->error_message);
            $this->assertNull($export->fresh()->file_path);
        } else {
            $this->assertSame($before, $export->fresh()->getRawOriginal());
        }
        $this->assertDatabaseCount('app_notifications', 0);
        Queue::assertNothingPushed();
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function legacyDumpStates(): array
    {
        return ['pending legacy' => [DatabaseExport::STATUS_PENDING, false, false],
            'pending callback first' => [DatabaseExport::STATUS_PENDING, true, false],
            'pending with artifact reference' => [DatabaseExport::STATUS_PENDING, false, true],
            'active legacy' => [DatabaseExport::STATUS_PROCESSING, false, false],
            'completed legacy' => [DatabaseExport::STATUS_COMPLETED, false, false],
            'failed legacy' => [DatabaseExport::STATUS_FAILED, false, false]];
    }

    /** @param array<string, mixed> $attributes */
    private function export(array $attributes = []): DatabaseExport
    {
        return DatabaseExport::query()->create(array_replace([
            'user_id' => User::factory()->create()->id,
            'export_type' => DatabaseExport::TYPE_FULL,
            'status' => DatabaseExport::STATUS_PENDING,
        ], $attributes));
    }

    private function candidate(DatabaseExport $export): string
    {
        return 'database-exports/'.$export->uuid.'/'.Str::uuid().'.sql';
    }

    private function assertOwnedCandidate(DatabaseExport $export): void
    {
        $this->assertIsString($export->file_path);
        $prefix = 'database-exports/'.$export->uuid.'/';
        $this->assertStringStartsWith($prefix, $export->file_path);
        $this->assertStringEndsWith('.sql', $export->file_path);
        $this->assertTrue(Str::isUuid(substr($export->file_path, strlen($prefix), -4)));
    }

    private function assertFailedAndClean(DatabaseExport $export): void
    {
        $current = $export->fresh();
        $this->assertNotNull($current);
        $this->assertSame(DatabaseExport::STATUS_FAILED, $current->status);
        $this->assertNotEmpty($current->error_message);
        $this->assertStringNotContainsString('OPS001', $current->error_message);
        $this->assertNull($current->file_path);
        $this->assertSame([], Storage::disk('local')->allFiles('database-exports/'.$export->uuid));
    }

    private function runExpectedFailure(ProcessDatabaseExport $job): ?RuntimeException
    {
        try {
            $job->handle();
        } catch (RuntimeException $exception) {
            // A one-attempt job may propagate after persisting its observable failure.
            return $exception;
        }

        return null;
    }

    private function outputAdapter(stdClass $observed, ?string $failure = null): FilesystemAdapter
    {
        $base = Storage::disk('local');
        $observed->stream = null;
        Storage::set('local', new class($base, $observed, $failure) extends FilesystemAdapter
        {
            public function __construct(FilesystemAdapter $base, private readonly stdClass $observed, private readonly ?string $failure)
            {
                parent::__construct($base->getDriver(), $base->getAdapter(), $base->getConfig());
            }

            public function put($path, $contents, $options = []): bool
            {
                $this->observed->stream = $contents;

                if ($this->failure === 'put-false') {
                    return false;
                }

                if ($this->failure === 'put-throw') {
                    throw new RuntimeException('OPS001 private publication exception');
                }

                if ($this->failure === 'put-truncated') {
                    return parent::put($path, 'OPS001 truncated');
                }

                return parent::put($path, $contents, $options);
            }

            public function size($path): int
            {
                if ($this->failure === 'size-throw') {
                    throw new RuntimeException('OPS001 private size exception');
                }

                return parent::size($path);
            }

            public function delete($paths): bool
            {
                if ($this->failure === 'delete-false') {
                    return false;
                }

                if ($this->failure === 'delete-throw') {
                    throw new RuntimeException('OPS001 controlled cleanup exception');
                }

                return parent::delete($paths);
            }
        });

        return $base;
    }
}

final class InertBudgetDumper extends Sqlite
{
    public const string CONTENTS = "-- OPS001 inert fixture, no database was dumped.\nSELECT 1;\n";

    public int $calls = 0;

    public string $temporaryPath = '';

    public ?Closure $beforeWrite = null;

    public ?Throwable $failure = null;

    public function dumpToFile(string $dumpFile): void
    {
        $this->calls++;
        $this->temporaryPath = $dumpFile;
        ($this->beforeWrite ?? static fn () => null)();

        if ($this->failure !== null) {
            file_put_contents($dumpFile, 'OPS001 partial inert fixture');

            throw $this->failure;
        }

        file_put_contents($dumpFile, self::CONTENTS);
    }
}

final class InertDatabaseExportJob extends ProcessDatabaseExport
{
    public function __construct(DatabaseExport $export, private readonly InertBudgetDumper $dumper)
    {
        parent::__construct($export);
    }

    public function handle(): void
    {
        $driver = config('database.connections.sqlite.driver');
        config(['database.connections.sqlite.driver' => 'unsupported-OPS001-inert-fixture']);

        try {
            parent::handle();
        } finally {
            config(['database.connections.sqlite.driver' => $driver]);
        }
    }

    /** @param array<string, mixed> $config */
    protected function buildDumper(string $driver, array $config, DatabaseExport $export): Sqlite
    {
        return $this->dumper;
    }
}
