<?php

namespace Tests\Feature\Export;

use App\Events\NotificationCreated;
use App\Jobs\ProcessExport;
use App\Models\Export;
use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Permissions\Models\Permission;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ExportProcessingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private mixed $originalEvents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
        Storage::fake('local');
        Event::fake([NotificationCreated::class]);
        config(['exports.chunk_size' => 2]);
        $this->originalEvents = Export::getEventDispatcher();
        Export::setEventDispatcher(clone $this->originalEvents);
    }

    protected function tearDown(): void
    {
        Export::setEventDispatcher($this->originalEvents);
        parent::tearDown();
    }

    public function test_second_delivery_during_generation_cannot_write_or_notify(): void
    {
        [, $export] = $this->fixture();
        $entered = false;
        Export::updated(function (Export $current) use ($export, &$entered): void {
            if ($current->id === $export->id && $current->wasChanged('processed_records') && ! $entered) {
                $entered = true;
                $before = $current->fresh()->getRawOriginal();
                (new ProcessExport($current->fresh()))->handle();
                $this->assertSame($before, $current->fresh()->getRawOriginal());
            }
        });
        (new ProcessExport($export))->handle();
        $this->assertTrue($entered);
        $this->assertReady($export);
        $this->assertDatabaseCount('app_notifications', 1);
    }

    public function test_late_failed_callback_never_downgrades_a_completed_export(): void
    {
        [, $export] = $this->fixture();
        $old = unserialize(serialize(new ProcessExport($export)));
        (new ProcessExport($export))->handle();
        $before = $export->fresh()->getRawOriginal();
        $bytes = Storage::disk('local')->get($export->fresh()->file_path);
        $old->failed(new RuntimeException('OPS001_PRIVATE_SENTINEL delayed callback'));
        $this->assertSame($before, $export->fresh()->getRawOriginal());
        $this->assertSame($bytes, Storage::disk('local')->get($export->fresh()->file_path));
        $this->assertDatabaseCount('app_notifications', 1);
    }

    public function test_rehydrated_failed_callback_cannot_fail_a_new_active_lease(): void
    {
        [, $export] = $this->fixture();
        $old = unserialize(serialize(new ProcessExport($export)));
        $token = (string) Str::uuid();
        $export->forceFill(['status' => Export::STATUS_PROCESSING, 'processing_token' => $token, 'processing_expires_at' => now()->addMinutes(10), 'processed_records' => 3])->save();
        $before = $export->fresh()->getRawOriginal();
        $old->failed(new RuntimeException('OPS001 old queue callback'));
        $this->assertSame($before, $export->fresh()->getRawOriginal());
        (new ProcessExport($export))->handle();
        $this->assertSame($before, $export->fresh()->getRawOriginal());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('app_notifications', 0);
    }

    public function test_an_old_producer_cannot_publish_over_a_replacement_lease(): void
    {
        [, $export] = $this->fixture();
        $changed = false;
        $token = (string) Str::uuid();
        Export::updated(function (Export $current) use ($export, $token, &$changed): void {
            if ($current->id === $export->id && $current->wasChanged('processed_records') && ! $changed) {
                $changed = true;
                Export::whereKey($export->id)->update(['processing_token' => $token, 'processing_expires_at' => now()->addMinutes(10), 'processed_records' => 77]);
            }
        });
        try {
            (new ProcessExport($export))->handle();
        } catch (RuntimeException) {
        }
        $this->assertTrue($changed);
        $this->assertSame($token, $export->fresh()->processing_token);
        $this->assertSame(77, $export->fresh()->processed_records);
        $this->assertSame(Export::STATUS_PROCESSING, $export->fresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('app_notifications', 0);
    }

    #[DataProvider('deletions')]
    public function test_deletion_during_processing_cancels_publication_without_resurrecting_a_record(string $target, bool $afterPut): void
    {
        [$owner, $export] = $this->fixture();
        $deleted = false;
        $delete = function () use ($target, $owner, $export, &$deleted): void {
            if ($deleted) {
                return;
            }
            $deleted = true;
            $target === 'owner' ? $owner->delete() : $export->delete();
        };
        if ($afterPut) {
            $this->observePut($delete);
        } else {
            Export::updated(function (Export $current) use ($export, $delete): void {
                if ($current->id === $export->id && $current->wasChanged('processed_records')) {
                    $delete();
                }
            });
        }
        try {
            (new ProcessExport($export))->handle();
        } catch (RuntimeException) {
        }
        $this->assertTrue($deleted);
        $this->assertModelMissing($export);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('app_notifications', 0);
        $this->assertNothingExternal();
    }

    /** @return array<string, array{string, bool}> */
    public static function deletions(): array
    {
        return ['row during writer' => ['export', false], 'cascade during writer' => ['owner', false], 'row after put' => ['export', true], 'cascade after put' => ['owner', true]];
    }

    public function test_notice_insert_failure_rolls_back_completion_and_retry_creates_one_notice(): void
    {
        [, $export] = $this->fixture();
        $events = AppNotification::getEventDispatcher();
        AppNotification::setEventDispatcher(clone $events);
        /** @var bool $fail */
        $fail = true;
        AppNotification::creating(function () use (&$fail): void {
            if ($fail) {
                throw new RuntimeException('OPS001_PRIVATE_SENTINEL notice insert');
            }
        });
        try {
            try {
                (new ProcessExport($export))->handle();
                $this->fail('A notice persistence failure must reach the retry boundary.');
            } catch (RuntimeException $exception) {
                $this->assertSame('OPS001_PRIVATE_SENTINEL notice insert', $exception->getMessage());
            }
            $this->assertSame(Export::STATUS_PROCESSING, $export->fresh()->status);
            $this->assertTrue($export->fresh()->processing_expires_at->isPast() || $export->fresh()->processing_expires_at->isCurrentSecond());
            $this->assertNull($export->fresh()->file_path);
            $this->assertDatabaseCount('app_notifications', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
            Event::assertNotDispatched(NotificationCreated::class);
            $fail = false;
            (new ProcessExport($export))->handle();
            $this->assertReady($export);
            $this->assertDatabaseCount('app_notifications', 1);
            Event::assertDispatchedTimes(NotificationCreated::class, 1);
        } finally {
            AppNotification::setEventDispatcher($events);
        }
    }

    public function test_broadcast_failure_after_commit_does_not_revert_the_ready_file_or_notice(): void
    {
        [, $export] = $this->fixture();
        Broadcast::shouldReceive('event')->once()->with(Mockery::type(NotificationCreated::class))->andThrow(new RuntimeException('OPS001 realtime outage'));
        (new ProcessExport($export))->handle();
        $this->assertReady($export);
        $this->assertDatabaseCount('app_notifications', 1);
        (new ProcessExport($export))->handle();
        $this->assertDatabaseCount('app_notifications', 1);
    }

    public function test_publication_false_is_not_a_completed_export_and_retry_uses_another_generation(): void
    {
        [, $export] = $this->fixture();
        $base = Storage::disk('local');
        $this->observePut(fn () => null, true);
        try {
            (new ProcessExport($export))->handle();
            $this->fail('A refused publication must fail.');
        } catch (RuntimeException) {
        }
        $old = $export->fresh()->processing_token;
        $this->assertNotSame(Export::STATUS_COMPLETED, $export->fresh()->status);
        $this->assertDatabaseCount('app_notifications', 0);
        Storage::set('local', $base);
        (new ProcessExport($export))->handle();
        $this->assertReady($export);
        $this->assertNotSame($old, $export->fresh()->processing_token);
    }

    public function test_prune_never_deletes_an_active_generation_even_with_stale_availability_metadata(): void
    {
        [, $export] = $this->fixture();
        $export->forceFill(['status' => Export::STATUS_PROCESSING, 'expires_at' => now()->subHour(), 'started_at' => now()])->save();
        $before = $export->fresh()->getRawOriginal();
        $this->artisan('exports:prune')->assertSuccessful();
        $this->assertSame($before, $export->fresh()->getRawOriginal());
    }

    public function test_recovery_enqueues_an_expired_export_once_without_restarting_terminal_work(): void
    {
        [, $export] = $this->fixture();
        $export->forceFill(['status' => Export::STATUS_PROCESSING, 'processing_token' => (string) Str::uuid(), 'processing_expires_at' => now()->subSecond()])->save();
        Bus::fake([ProcessExport::class]);
        config(['queue.default' => 'database', 'queue.connections.database' => ['driver' => 'database', 'connection' => 'sqlite', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 1020, 'after_commit' => false]]);
        $this->artisan('exports:recover')->assertSuccessful();
        $this->artisan('exports:recover')->assertSuccessful();
        $this->assertSame(Export::STATUS_PENDING, $export->fresh()->status);
        Bus::assertDispatched(ProcessExport::class, 1);
    }

    public function test_old_payload_failed_callback_cannot_cancel_a_recovery_waiting_to_be_claimed(): void
    {
        [, $export] = $this->fixture();
        $old = unserialize(serialize(new ProcessExport($export)));
        $export->forceFill(['status' => Export::STATUS_PROCESSING, 'processing_token' => (string) Str::uuid(), 'processing_expires_at' => now()->subSecond()])->save();
        Bus::fake([ProcessExport::class]);
        config(['queue.default' => 'database', 'queue.connections.database' => ['driver' => 'database', 'connection' => 'sqlite', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 1020, 'after_commit' => false]]);
        $this->artisan('exports:recover')->assertSuccessful();
        $before = $export->fresh()->getRawOriginal();
        $old->failed(new RuntimeException('OPS001 old payload after recovery'));
        $this->assertSame($before, $export->fresh()->getRawOriginal());
        $this->assertSame(Export::STATUS_PENDING, $export->fresh()->status);
        (new ProcessExport($export->fresh()))->handle();
        $this->assertReady($export);
    }

    public function test_rehydrated_callback_from_an_older_queue_attempt_cannot_fail_a_new_expired_generation(): void
    {
        [, $export] = $this->fixture();
        $old = unserialize(serialize(new ProcessExport($export)));
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('getJobId')->andReturn('entry-1');
        $queueJob->shouldReceive('getConnectionName')->andReturn('database');
        $queueJob->shouldReceive('getQueue')->andReturn('default');
        $old->setJob($queueJob);
        $export->forceFill(['status' => Export::STATUS_PROCESSING, 'processing_token' => (string) Str::uuid(), 'processing_attempt' => 2,
            'processing_job_key' => hash('sha256', "database\0default\0entry-1"), 'processing_expires_at' => now()->subSecond()])->save();
        $before = $export->fresh()->getRawOriginal();
        $old->failed(new RuntimeException('OPS001 older attempt callback'));
        $this->assertSame($before, $export->fresh()->getRawOriginal());
    }

    public function test_callback_from_a_distinct_queue_entry_cannot_fail_a_generation_with_the_same_attempt_number(): void
    {
        [, $export] = $this->fixture();
        $old = unserialize(serialize(new ProcessExport($export)));
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('getJobId')->andReturn('entry-1');
        $queueJob->shouldReceive('getConnectionName')->andReturn('database');
        $queueJob->shouldReceive('getQueue')->andReturn('default');
        $old->setJob($queueJob);
        $export->forceFill(['status' => Export::STATUS_PROCESSING, 'processing_token' => (string) Str::uuid(), 'processing_attempt' => 1,
            'processing_job_key' => hash('sha256', "database\0default\0entry-2"), 'processing_expires_at' => now()->subSecond()])->save();
        $before = $export->fresh()->getRawOriginal();
        $old->failed(new RuntimeException('OPS001 distinct entry callback'));
        $this->assertSame($before, $export->fresh()->getRawOriginal());
    }

    #[DataProvider('queueCallbackIds')]
    public function test_rehydrated_failed_callback_requires_a_known_matching_queue_id(string|int|null $id, bool $mayFail): void
    {
        [, $export] = $this->fixture();
        $old = unserialize(serialize(new ProcessExport($export)));
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('getJobId')->andReturn($id);
        $queueJob->shouldReceive('getConnectionName')->andReturn('database');
        $queueJob->shouldReceive('getQueue')->andReturn('default');
        $queueJob->shouldReceive('attempts')->andReturn(4);
        $old->setJob($queueJob);
        $export->forceFill(['status' => Export::STATUS_PROCESSING, 'processing_token' => (string) Str::uuid(), 'processing_attempt' => 3,
            'processing_job_key' => hash('sha256', "database\0default\0".'1'), 'processing_expires_at' => now()->subSecond()])->save();
        $before = $export->fresh()->getRawOriginal();
        $old->failed(new RuntimeException('OPS001 private final attempt'));
        if ($mayFail) {
            $this->assertSame(Export::STATUS_FAILED, $export->fresh()->status);
            $this->assertSame('Export could not be completed. Please request a new export.', $export->fresh()->error_message);
        } else {
            $this->assertSame($before, $export->fresh()->getRawOriginal());
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('app_notifications', 0);
    }

    /** @return array<string, array{string|int|null, bool}> */
    public static function queueCallbackIds(): array
    {
        return ['integer ID' => [1, true], 'string ID' => ['1', true], 'empty ID' => ['', false], 'missing ID' => [null, false]];
    }

    public function test_recovery_republishes_an_abandoned_pending_delivery_and_fences_the_old_payload(): void
    {
        [, $export] = $this->fixture();
        $old = unserialize(serialize(new ProcessExport($export)));
        $token = $export->dispatch_token;
        Export::whereKey($export->id)->update(['updated_at' => now()->subSeconds(1021)]);
        Bus::fake([ProcessExport::class]);
        config(['queue.default' => 'database', 'queue.connections.database' => ['driver' => 'database', 'connection' => 'sqlite', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 1020, 'after_commit' => false]]);
        $this->artisan('exports:recover')->assertSuccessful();
        $this->artisan('exports:recover')->assertSuccessful();
        $this->assertNotSame($token, $export->fresh()->dispatch_token);
        Bus::assertDispatched(ProcessExport::class, 1);
        $old->handle();
        $old->failed(new RuntimeException('OPS001 old abandoned pending callback'));
        $this->assertSame(Export::STATUS_PENDING, $export->fresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles());
        (new ProcessExport($export->fresh()))->handle();
        $this->assertReady($export);
    }

    public function test_failed_recovery_queue_dispatch_leaves_a_pending_record_that_can_be_republished_later(): void
    {
        [, $export] = $this->fixture();
        $export->forceFill(['status' => Export::STATUS_PROCESSING, 'processing_token' => (string) Str::uuid(), 'processing_expires_at' => now()->subSecond()])->save();
        config(['queue.default' => 'database', 'queue.connections.database' => ['driver' => 'database', 'connection' => 'sqlite', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 1020, 'after_commit' => false]]);
        $dispatcher = Bus::getFacadeRoot();
        Bus::shouldReceive('dispatch')->once()->with(Mockery::type(ProcessExport::class))->andThrow(new RuntimeException('OPS001 queue publication refused'));
        try {
            try {
                $this->artisan('exports:recover')->run();
                $this->fail('An after-commit queue publication failure must be reported.');
            } catch (RuntimeException $exception) {
                $this->assertSame('OPS001 queue publication refused', $exception->getMessage());
            }
        } finally {
            Bus::swap($dispatcher);
        }
        $recovered = $export->fresh();
        $this->assertSame(Export::STATUS_PENDING, $recovered->status);
        $this->assertNotSame($export->dispatch_token, $recovered->dispatch_token);
        $token = $recovered->dispatch_token;
        Bus::fake([ProcessExport::class]);
        $this->artisan('exports:recover')->assertSuccessful();
        Bus::assertNothingDispatched();
        Export::whereKey($export->id)->update(['updated_at' => now()->subSeconds(1021)]);
        $this->artisan('exports:recover')->assertSuccessful();
        Bus::assertDispatched(ProcessExport::class, 1);
        $this->assertNotSame($token, $export->fresh()->dispatch_token);
        $this->assertNothingExternal();
    }

    public function test_export_lease_migration_rolls_back_and_reapplies_without_losing_legacy_rows(): void
    {
        [, $export] = $this->fixture();
        $migration = require dirname(__DIR__, 3).'/database/migrations/2026_10_18_190650_add_processing_lease_to_exports_table.php';
        $migration->down();
        $this->assertFalse(Schema::hasColumn('exports', 'processing_token'));
        $this->assertDatabaseHas('exports', ['id' => $export->id, 'uuid' => $export->uuid, 'status' => Export::STATUS_PENDING]);
        $migration->up();
        $this->assertTrue(Schema::hasColumn('exports', 'processing_token'));
        $this->assertNull($export->fresh()->processing_token);
        $this->assertNull($export->fresh()->dispatch_token);
        $this->assertSame(0, $export->fresh()->processing_attempt);
    }

    /** @return array{User, Export} */
    private function fixture(): array
    {
        $owner = User::factory()->create(['must_change_password' => false, 'email_verified_at' => now()]);
        $owner->permissions()->attach(Permission::firstOrCreate(['slug' => 'view-clients'], ['name' => 'View clients', 'group' => 'clients']));
        foreach (range(1, 6) as $i) {
            Client::factory()->create(['name' => 'OPS001 client '.$i]);
        }
        $export = Export::factory()->create(['user_id' => $owner->id, 'model_class' => Client::class, 'format' => 'csv', 'columns' => ['name'], 'sort_column' => 'id', 'sort_direction' => 'asc']);

        return [$owner, $export];
    }

    private function assertReady(Export $export): void
    {
        $export->refresh();
        $this->assertSame(Export::STATUS_COMPLETED, $export->status);
        $this->assertNotNull($export->processing_token);
        $this->assertSame(6, $export->processed_records);
        $this->assertSame(6, $export->total_records);
        $bytes = Storage::disk('local')->get($export->file_path);
        $this->assertSame(1, substr_count($bytes, 'OPS001 client 1'));
        foreach (range(1, 6) as $i) {
            $this->assertStringContainsString('OPS001 client '.$i, $bytes);
        }
        $this->assertSame([$export->file_path], Storage::disk('local')->allFiles());
        $this->assertNothingExternal();
    }

    private function observePut(\Closure $callback, bool $refuse = false): void
    {
        $base = Storage::disk('local');
        Storage::set('local', new class($base, $callback, $refuse) extends FilesystemAdapter
        {
            public function __construct(FilesystemAdapter $base, private readonly \Closure $callback, private readonly bool $refuse)
            {
                parent::__construct($base->getDriver(), $base->getAdapter(), $base->getConfig());
            }

            public function put($path, $contents, $options = []): bool
            {
                if ($this->refuse) {
                    return false;
                }
                $result = parent::put($path, $contents, $options);
                ($this->callback)();

                return $result;
            }
        });
    }

    private function assertNothingExternal(): void
    {
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }
}
