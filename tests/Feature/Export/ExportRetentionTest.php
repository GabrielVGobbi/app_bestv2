<?php

namespace Tests\Feature\Export;

use App\Models\Export;
use App\Models\User;
use App\Modules\Backup\Models\DatabaseExport;
use App\Supports\PrivateExportFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\FilesystemAdapter as FlysystemAdapter;
use League\Flysystem\FilesystemOperator;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ExportRetentionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
        Carbon::setTestNow(Carbon::parse('2030-02-15 12:00:00', date_default_timezone_get()));
        Storage::fake('local');
        Notification::fake();
        config(['exports.disk' => 'local', 'exports.directory' => 'exports', 'database-exports.disk' => 'local',
            'database-exports.directory' => 'database-exports', 'filesystems.disks.local.visibility' => 'private']);
        $this->owner = User::factory()->create();
    }

    protected function tearDown(): void
    {
        try {
            Carbon::setTestNow();
        } finally {
            parent::tearDown();
        }
    }

    #[DataProvider('artifactKinds')]
    public function test_prune_removes_all_2001_expired_records_and_their_private_files_without_skipping_batches(string $kind): void
    {
        $seed = $this->record($kind);
        $table = $seed->getTable();
        $template = $seed->getAttributes();
        unset($template['id']);
        $paths = [$seed->file_path];
        $batch = [];
        for ($index = 0; $index < 2000; $index++) {
            $uuid = (string) Str::uuid();
            $path = $this->flatPath($kind, $uuid);
            $batch[] = [...$template, 'uuid' => $uuid, 'file_path' => $path];
            $paths[] = $path;
            Storage::disk('local')->put($path, 'EXP002 private bulk fixture');
            if (count($batch) === 20) {
                $seed->getConnection()->table($table)->insert($batch);
                $batch = [];
            }
        }
        $this->assertDatabaseCount($table, 2001);
        $this->assertCount(2001, Storage::disk('local')->allFiles($this->directory($kind)));

        $this->artisan($this->command($kind))->assertExitCode(0);

        $this->assertDatabaseCount($table, 0);
        $this->assertSame([], Storage::disk('local')->allFiles($this->directory($kind)));
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
        $this->assertIsolated();
    }

    #[DataProvider('artifactKinds')]
    public function test_delete_false_preserves_the_expired_row_and_path_and_reports_failure(string $kind): void
    {
        $record = $this->record($kind);
        $before = $record->fresh()->getRawOriginal();
        $disk = Storage::disk('local');
        $this->failDelete($record->file_path, 'false');

        $this->artisan($this->command($kind))->assertExitCode(1);

        $this->assertSame($before, $record->fresh()->getRawOriginal());
        $this->assertSame('EXP002 private fixture', $disk->get($record->file_path));
        Storage::set('local', $disk);
        $this->artisan($this->command($kind))->assertExitCode(0);
        $this->assertNull($record->fresh());
        $disk->assertMissing($record->file_path);
        $this->assertIsolated();
    }

    #[DataProvider('artifactKinds')]
    public function test_delete_exceptions_preserve_control_and_do_not_skip_other_expired_records(string $kind): void
    {
        $failed = $this->record($kind);
        $later = $this->record($kind);
        $before = $failed->fresh()->getRawOriginal();
        $base = Storage::disk('local');
        $this->failDelete($failed->file_path, 'throw');

        $this->artisan($this->command($kind))->assertExitCode(1);

        $this->assertSame($before, $failed->fresh()->getRawOriginal());
        $this->assertSame('EXP002 private fixture', $base->get($failed->file_path));
        $this->assertNull($later->fresh());
        $base->assertMissing($later->file_path);
        Storage::set('local', $base);
        $this->artisan($this->command($kind))->assertExitCode(0);
        $this->assertNull($failed->fresh());
        $this->assertIsolated();
    }

    public function test_export_pruning_preserves_future_completed_and_all_pending_or_processing_requests(): void
    {
        $expired = $this->record('export');
        $future = $this->record('export', ['expires_at' => now()->addHour()]);
        $pending = $this->record('export', ['status' => 'pending', 'completed_at' => null, 'updated_at' => now()->subDays(20)]);
        $processing = $this->record('export', ['status' => 'processing', 'completed_at' => null,
            'processing_token' => (string) Str::uuid(), 'processing_expires_at' => now()->subDays(20)]);
        $before = [];
        foreach ([$future, $pending, $processing] as $record) {
            $before[$record->id] = $record->fresh()->getRawOriginal();
        }
        $this->assertTrue($future->fresh()->expires_at->isFuture(), 'The fixture clock must agree with persisted datetime casts.');

        $this->artisan('exports:prune')->assertExitCode(0);

        $this->assertNull($expired->fresh());
        foreach ([$future, $pending, $processing] as $record) {
            $this->assertSame($before[$record->id], $record->fresh()->getRawOriginal());
            $this->assertSame('EXP002 private fixture', Storage::disk('local')->get($record->file_path));
        }
        Queue::assertNothingPushed();
        $this->assertIsolated();
    }

    public function test_dump_reconciliation_respects_pending_and_processing_budgets_and_terminal_retention(): void
    {
        $pendingFresh = $this->record('dump', ['status' => 'pending', 'file_path' => null, 'expires_at' => null, 'completed_at' => null]);
        $pendingOld = $this->record('dump', ['status' => 'pending', 'file_path' => null, 'expires_at' => null, 'completed_at' => null]);
        $processingFresh = $this->record('dump', ['status' => 'processing', 'expires_at' => null, 'completed_at' => null, 'started_at' => now()->subSeconds(899)]);
        $processingOld = $this->record('dump', ['status' => 'processing', 'expires_at' => null, 'completed_at' => null, 'started_at' => now()->subSeconds(901)]);
        $legacyFresh = $this->record('dump', ['status' => 'processing', 'expires_at' => null, 'completed_at' => null, 'started_at' => null]);
        $legacyOld = $this->record('dump', ['status' => 'processing', 'expires_at' => null, 'completed_at' => null, 'started_at' => null]);
        $pendingFresh->getConnection()->table('database_exports')->where('id', $pendingFresh->id)->update(['updated_at' => now()->subSeconds(1019)]);
        $pendingOld->getConnection()->table('database_exports')->where('id', $pendingOld->id)->update(['updated_at' => now()->subSeconds(1021)]);
        $processingFresh->getConnection()->table('database_exports')->where('id', $processingFresh->id)->update(['updated_at' => now()->subDays(2)]);
        $legacyFresh->getConnection()->table('database_exports')->where('id', $legacyFresh->id)->update(['updated_at' => now()->subSeconds(899)]);
        $legacyOld->getConnection()->table('database_exports')->where('id', $legacyOld->id)->update(['updated_at' => now()->subSeconds(901)]);
        $preserved = [];
        foreach ([$pendingFresh, $processingFresh, $legacyFresh] as $record) {
            $preserved[$record->id] = $record->fresh()->getRawOriginal();
        }
        $this->assertTrue($pendingFresh->fresh()->updated_at->gt(now()->subSeconds(1020)));
        $this->assertTrue($pendingOld->fresh()->updated_at->lte(now()->subSeconds(1020)));
        $this->assertTrue($processingFresh->fresh()->started_at->gt(now()->subSeconds(900)));
        $this->assertTrue($processingOld->fresh()->started_at->lte(now()->subSeconds(900)));
        $this->assertTrue($legacyFresh->fresh()->updated_at->gt(now()->subSeconds(900)));
        $this->assertTrue($legacyOld->fresh()->updated_at->lte(now()->subSeconds(900)));

        $this->artisan('database-exports:prune')->assertExitCode(0);

        foreach ([$pendingFresh, $processingFresh, $legacyFresh] as $record) {
            $this->assertSame($preserved[$record->id], $record->fresh()->getRawOriginal());
        }
        foreach ([$pendingOld, $processingOld, $legacyOld] as $record) {
            $current = $record->fresh();
            $this->assertSame('failed', $current->status);
            $this->assertNull($current->file_path);
            $this->assertSame(now()->toDateTimeString(), $current->updated_at->toDateTimeString());
            if (is_string($record->file_path)) {
                Storage::disk('local')->assertMissing($record->file_path);
            }
        }
        Carbon::setTestNow(now()->addDay());
        $this->artisan('database-exports:prune')->assertExitCode(0);
        foreach ([$pendingOld, $processingOld, $legacyOld] as $record) {
            $this->assertSame('2030-02-15 12:00:00', $record->fresh()->updated_at->toDateTimeString());
        }
        $this->assertIsolated();
    }

    #[DataProvider('artifactKinds')]
    public function test_failed_records_keep_the_terminal_timestamp_and_expire_after_seven_days(string $kind): void
    {
        $record = $this->record($kind, ['status' => 'failed', 'expires_at' => null, 'completed_at' => null,
            'error_message' => 'EXP002 terminal diagnostic']);
        $terminal = now()->subDays(6)->toDateTimeString();
        $record->getConnection()->table($record->getTable())->where('id', $record->id)->update(['updated_at' => $terminal]);

        $this->artisan($this->command($kind))->assertExitCode(0);

        $current = $record->fresh();
        $this->assertSame('failed', $current->status);
        $this->assertNull($current->file_path);
        $this->assertSame($terminal, $current->updated_at->toDateTimeString());
        $this->assertSame('EXP002 terminal diagnostic', $current->error_message);
        Storage::disk('local')->assertMissing($record->file_path);
        $this->artisan($this->command($kind))->assertExitCode(0);
        $this->assertSame($terminal, $record->fresh()->updated_at->toDateTimeString());
        Carbon::setTestNow(now()->addDays(2));
        $this->artisan($this->command($kind))->assertExitCode(0);
        $this->assertNull($record->fresh());
        $this->assertIsolated();
    }

    #[DataProvider('artifactKinds')]
    public function test_failed_retention_never_deletes_control_while_its_private_artifact_cannot_be_removed(string $kind): void
    {
        $record = $this->record($kind, ['status' => 'failed', 'expires_at' => null]);
        $record->getConnection()->table($record->getTable())->where('id', $record->id)->update(['updated_at' => now()->subDays(8)]);
        $before = $record->fresh()->getRawOriginal();
        $base = Storage::disk('local');
        $this->failDelete($record->file_path, 'false');

        $this->artisan($this->command($kind))->assertExitCode(1);

        $this->assertSame($before, $record->fresh()->getRawOriginal());
        $this->assertSame('EXP002 private fixture', $base->get($record->file_path));
        Storage::set('local', $base);
        $this->artisan($this->command($kind))->assertExitCode(0);
        $this->assertNull($record->fresh());
        $this->assertIsolated();
    }

    #[DataProvider('artifactKinds')]
    public function test_only_old_recognized_orphans_without_a_registered_uuid_are_removed(string $kind): void
    {
        $directory = $this->directory($kind);
        $extensions = $kind === 'export' ? ['csv', 'xlsx', 'tsv'] : ['sql'];
        $old = [];
        foreach ($extensions as $extension) {
            $old[] = $directory.'/'.Str::uuid().'.'.$extension;
            $old[] = $directory.'/'.Str::uuid().'/'.Str::uuid().'.'.$extension;
            $old[] = $directory.'/'.Str::uuid().'.'.$extension.'.part';
            $old[] = $directory.'/'.Str::uuid().'/'.Str::uuid().'.'.$extension.'.part';
        }
        foreach ($old as $path) {
            $this->putWithMtime($path, now()->subDay()->timestamp);
        }
        $registered = $this->record($kind, ['expires_at' => now()->addDay()]);
        $before = $registered->fresh()->getRawOriginal();
        $extension = $extensions[0];
        $keep = [$registered->file_path, $directory.'/'.$registered->uuid.'/'.Str::uuid().'.'.$extension.'.part',
            $directory.'/README.txt', $directory.'/not-a-uuid.'.$extension, $directory.'/'.Str::uuid().'.log',
            $directory.'/'.Str::uuid().'/unknown.'.$extension, 'other-artifacts/'.Str::uuid().'.'.$extension];
        foreach ($keep as $path) {
            $this->putWithMtime($path, now()->subDays(2)->timestamp);
        }
        $young = [$directory.'/'.Str::uuid().'.'.$extension, $directory.'/'.Str::uuid().'/'.Str::uuid().'.'.$extension.'.part'];
        foreach ($young as $path) {
            $this->putWithMtime($path, now()->subHours(23)->timestamp);
        }

        $this->artisan($this->command($kind))->assertExitCode(0);

        foreach ($old as $path) {
            Storage::disk('local')->assertMissing($path);
        }
        foreach ([...$keep, ...$young] as $path) {
            $this->assertSame('EXP002 filesystem sentinel', Storage::disk('local')->get($path));
        }
        $this->assertSame($before, $registered->fresh()->getRawOriginal());
        $this->artisan($this->command($kind))->assertExitCode(0);
        $this->assertSame($before, $registered->fresh()->getRawOriginal());
        $this->assertIsolated();
    }

    #[DataProvider('artifactKinds')]
    public function test_an_eloquent_deleting_veto_preserves_the_record_and_returns_failure(string $kind): void
    {
        $record = $this->record($kind);
        $dispatcher = Model::getEventDispatcher();
        $events = clone $dispatcher;
        Model::setEventDispatcher($events);
        $events->listen('eloquent.deleting: '.$record::class, fn (Export|DatabaseExport $deleting): ?bool => $deleting->id === $record->id ? false : null);
        try {
            $this->artisan($this->command($kind))->assertExitCode(1);
            $this->assertNotNull($record->fresh());
            $this->assertSame($record->uuid, $record->fresh()->uuid);
        } finally {
            Model::setEventDispatcher($dispatcher);
        }
        $this->artisan($this->command($kind))->assertExitCode(0);
        $this->assertNull($record->fresh());
        $this->assertIsolated();
    }

    #[DataProvider('artifactKinds')]
    public function test_an_expired_row_cannot_delete_another_registered_uuid_artifact(string $kind): void
    {
        $foreign = $this->record($kind, ['expires_at' => now()->addDay()]);
        $record = $this->record($kind, ['file_path' => $foreign->file_path]);
        $before = $record->fresh()->getRawOriginal();
        $foreignBefore = $foreign->fresh()->getRawOriginal();

        $this->artisan($this->command($kind))->assertExitCode(1);

        $this->assertSame($before, $record->fresh()->getRawOriginal());
        $this->assertSame($foreignBefore, $foreign->fresh()->getRawOriginal());
        $this->assertSame('EXP002 private fixture', Storage::disk('local')->get($foreign->file_path));
        $this->assertIsolated();
    }

    #[DataProvider('artifactKinds')]
    public function test_hardlinked_private_artifacts_are_refused_without_losing_control(string $kind): void
    {
        $record = $this->record($kind);
        $before = $record->fresh()->getRawOriginal();
        $disk = Storage::disk('local');
        (new Filesystem)->ensureDirectoryExists($disk->path('sentinels'));
        $linked = $disk->path('sentinels/hardlink.bin');
        $this->assertTrue(link($disk->path($record->file_path), $linked));
        clearstatcache();
        $this->assertSame(2, lstat($linked)['nlink']);

        $this->artisan($this->command($kind))->assertExitCode(1);

        $this->assertSame($before, $record->fresh()->getRawOriginal());
        $this->assertSame('EXP002 private fixture', file_get_contents($linked));
        $this->assertSame('EXP002 private fixture', $disk->get($record->file_path));
        $this->assertIsolated();
    }

    #[DataProvider('unsafeStorageProfiles')]
    public function test_public_storage_names_and_public_local_roots_are_refused(string $kind, string $profile): void
    {
        if ($profile === 'public-root') {
            $configuration = ['driver' => 'local', 'root' => public_path('retention-files'), 'throw' => true, 'visibility' => 'private'];
            (new Filesystem)->ensureDirectoryExists($configuration['root']);
            config(['filesystems.disks.local' => $configuration]);
            Storage::set('local', Storage::build($configuration));
        }
        $record = $this->record($kind);
        $before = $record->fresh()->getRawOriginal();
        $name = 'local';
        if ($profile === 'public-name') {
            Storage::fake('public');
            config([$kind === 'export' ? 'exports.disk' : 'database-exports.disk' => 'public']);
            Storage::disk('public')->put($record->file_path, 'EXP002 private fixture');
            $name = 'public';
        }

        $this->artisan($this->command($kind))->assertExitCode(1);

        $this->assertSame($before, $record->fresh()->getRawOriginal());
        $this->assertSame('EXP002 private fixture', Storage::disk($name)->get($record->file_path));
        $this->assertIsolated();
    }

    /** @return array<string, array{string, string}> */
    public static function unsafeStorageProfiles(): array
    {
        return ['exports public disk' => ['export', 'public-name'], 'dumps public disk' => ['dump', 'public-name'],
            'exports public local root' => ['export', 'public-root'], 'dumps public local root' => ['dump', 'public-root']];
    }

    #[DataProvider('artifactKinds')]
    public function test_failed_cleanup_errors_do_not_renew_a_recent_terminal_diagnostic(string $kind): void
    {
        $record = $this->record($kind, ['status' => 'failed', 'expires_at' => null]);
        $terminal = now()->subDays(6)->toDateTimeString();
        $record->getConnection()->table($record->getTable())->where('id', $record->id)->update(['updated_at' => $terminal]);
        $before = $record->fresh()->getRawOriginal();
        $base = Storage::disk('local');
        $this->failDelete($record->file_path, 'throw');

        $this->artisan($this->command($kind))->assertExitCode(1);

        $this->assertSame($before, $record->fresh()->getRawOriginal());
        $this->assertSame('EXP002 private fixture', $base->get($record->file_path));
        Storage::set('local', $base);
        $this->artisan($this->command($kind))->assertExitCode(0);
        $this->assertSame($terminal, $record->fresh()->updated_at->toDateTimeString());
        $this->assertNull($record->fresh()->file_path);
        $this->assertIsolated();
    }

    #[DataProvider('orphanFailures')]
    public function test_orphan_delete_errors_leave_the_file_observable_and_return_failure(string $kind, string $failure): void
    {
        $path = $this->flatPath($kind, (string) Str::uuid());
        $this->putWithMtime($path, now()->subDays(2)->timestamp);
        $base = Storage::disk('local');
        $this->failDelete($path, $failure);

        $this->artisan($this->command($kind))->assertExitCode(1);

        $this->assertSame('EXP002 filesystem sentinel', $base->get($path));
        Storage::set('local', $base);
        $this->artisan($this->command($kind))->assertExitCode(0);
        $base->assertMissing($path);
        $this->assertIsolated();
    }

    /** @return array<string, array{string, string}> */
    public static function orphanFailures(): array
    {
        return ['export orphan delete false' => ['export', 'false'], 'dump orphan delete false' => ['dump', 'false'],
            'export orphan delete exception' => ['export', 'throw'], 'dump orphan delete exception' => ['dump', 'throw']];
    }

    #[DataProvider('artifactKinds')]
    public function test_a_synthetic_remote_disk_is_refused_before_listing_or_any_artifact_io(string $kind): void
    {
        $record = $this->record($kind);
        $before = $record->fresh()->getRawOriginal();
        $base = Storage::disk('local');
        $observer = (object) ['calls' => []];
        $configuration = [...$base->getConfig(), 'driver' => 's3'];
        config(['filesystems.disks.local.driver' => 's3']);
        Storage::set('local', new class($base, $configuration, $observer) extends FilesystemAdapter
        {
            /** @param array<string, mixed> $configuration */
            public function __construct(FilesystemAdapter $base, array $configuration, private readonly \stdClass $observer)
            {
                parent::__construct($base->getDriver(), $base->getAdapter(), $configuration);
            }

            public function path($path): string
            {
                $this->observer->calls[] = 'path';
                throw new RuntimeException('EXP002 synthetic remote path must not be resolved.');
            }

            public function allFiles($directory = null): array
            {
                $this->observer->calls[] = 'allFiles';
                throw new RuntimeException('EXP002 synthetic remote must not be listed.');
            }

            public function exists($path): bool
            {
                $this->observer->calls[] = 'exists';
                throw new RuntimeException('EXP002 synthetic remote must not be queried.');
            }

            public function delete($paths): bool
            {
                $this->observer->calls[] = 'delete';
                throw new RuntimeException('EXP002 synthetic remote must not be mutated.');
            }
        });
        try {
            $this->artisan($this->command($kind))->assertExitCode(1);
            $this->assertSame([], $observer->calls);
            $this->assertSame($before, $record->fresh()->getRawOriginal());
            $this->assertSame('EXP002 private fixture', $base->get($record->file_path));
        } finally {
            Storage::set('local', $base);
            config(['filesystems.disks.local.driver' => 'local']);
        }
        $this->assertIsolated();
    }

    #[DataProvider('artifactKinds')]
    public function test_a_veto_on_clearing_failed_file_metadata_preserves_the_terminal_row_and_path(string $kind): void
    {
        $record = $this->record($kind, ['status' => 'failed', 'expires_at' => null]);
        $record->getConnection()->table($record->getTable())->where('id', $record->id)->update(['updated_at' => now()->subDays(6)]);
        $before = $record->fresh()->getRawOriginal();
        $dispatcher = Model::getEventDispatcher();
        $events = clone $dispatcher;
        Model::setEventDispatcher($events);
        $events->listen('eloquent.updating: '.$record::class, function (Export|DatabaseExport $updating) use ($record): ?bool {
            return $updating->id === $record->id && $updating->isDirty('file_path') && $updating->file_path === null ? false : null;
        });
        try {
            $this->artisan($this->command($kind))->assertExitCode(1);
            $this->assertSame($before, $record->fresh()->getRawOriginal());
        } finally {
            Model::setEventDispatcher($dispatcher);
        }
        $this->artisan($this->command($kind))->assertExitCode(0);
        $this->assertNull($record->fresh()->file_path);
        $this->assertSame($before['updated_at'], $record->fresh()->getRawOriginal('updated_at'));
        $this->assertIsolated();
    }

    #[DataProvider('artifactKinds')]
    public function test_a_failed_record_without_a_terminal_timestamp_is_retained_without_inventing_one(string $kind): void
    {
        $record = $this->record($kind, ['status' => 'failed', 'expires_at' => null]);
        $record->getConnection()->table($record->getTable())->where('id', $record->id)->update(['created_at' => now()->subDays(40), 'updated_at' => null]);

        $this->artisan($this->command($kind))->assertExitCode(0);

        $this->assertSame('failed', $record->fresh()->status);
        $this->assertNull($record->fresh()->file_path);
        $this->assertNull($record->fresh()->updated_at);
        Storage::disk('local')->assertMissing($record->file_path);
        Carbon::setTestNow(now()->addDays(40));
        $this->artisan($this->command($kind))->assertExitCode(0);
        $this->assertNotNull($record->fresh());
        $this->assertNull($record->fresh()->updated_at);
        $this->assertIsolated();
    }

    #[DataProvider('artifactKinds')]
    public function test_an_old_attempt_candidate_delete_failure_preserves_its_registered_control(string $kind): void
    {
        $record = $this->record($kind);
        $candidate = $this->directory($kind).'/'.$record->uuid.'/'.Str::uuid().($kind === 'export' ? '.csv' : '.sql');
        $this->putWithMtime($candidate, now()->subDays(2)->timestamp);
        $before = $record->fresh()->getRawOriginal();
        $base = Storage::disk('local');
        $this->failDelete($candidate, 'false');

        $this->artisan($this->command($kind))->assertExitCode(1);

        $this->assertSame($before, $record->fresh()->getRawOriginal());
        $this->assertSame('EXP002 filesystem sentinel', $base->get($candidate));
        Storage::set('local', $base);
        $this->artisan($this->command($kind))->assertExitCode(0);
        $this->assertNull($record->fresh());
        $base->assertMissing($candidate);
        $this->assertIsolated();
    }

    #[DataProvider('remoteStorageProfiles')]
    public function test_remote_exports_use_only_known_private_objects_while_dumps_refuse_remote_io(string $kind, string $visibility): void
    {
        $record = $this->record($kind);
        $before = $record->fresh()->getRawOriginal();
        $unrelated = $this->flatPath($kind, (string) Str::uuid());
        $state = (object) ['files' => [$record->file_path => 'EXP002 remote referenced', $record->file_path.'.part' => 'EXP002 remote part',
            $unrelated => 'EXP002 remote unrelated'], 'calls' => [], 'forbidden' => []];
        /** @var FlysystemAdapter&MockInterface $adapter */
        $adapter = Mockery::mock(FlysystemAdapter::class);
        foreach (['fileExists', 'directoryExists', 'write', 'writeStream', 'read', 'readStream', 'delete', 'deleteDirectory',
            'createDirectory', 'setVisibility', 'visibility', 'mimeType', 'lastModified', 'fileSize', 'listContents', 'move', 'copy'] as $method) {
            $adapter->shouldNotReceive($method);
        }
        $driver = new Flysystem($adapter);
        Storage::set('retention_remote', new class($driver, $adapter, $state, [$record->file_path, $record->file_path.'.part'], $visibility) extends FilesystemAdapter
        {
            /** @param list<string> $allowed */
            public function __construct(FilesystemOperator $driver, FlysystemAdapter $adapter, private readonly \stdClass $state,
                private readonly array $allowed, private readonly string $visibility)
            {
                parent::__construct($driver, $adapter, ['driver' => 's3', 'visibility' => 'private']);
            }

            private function observe(string $operation, string $path): void
            {
                if (! in_array($path, $this->allowed, true)) {
                    throw new RuntimeException('EXP002 remote access escaped the referenced objects.');
                }
                $this->state->calls[] = [$operation, $path];
            }

            public function exists($path): bool
            {
                $this->observe('exists', $path);

                return array_key_exists($path, $this->state->files);
            }

            public function getVisibility($path): string
            {
                $this->observe('visibility', $path);

                return $this->visibility;
            }

            public function delete($paths): bool
            {
                foreach ((array) $paths as $path) {
                    $this->observe('delete', $path);
                    unset($this->state->files[$path]);
                }

                return true;
            }

            public function path($path): string
            {
                $this->state->forbidden[] = 'path';
                throw new RuntimeException('EXP002 remote fixture has no local path.');
            }

            public function allFiles($directory = null): array
            {
                $this->state->forbidden[] = 'allFiles';
                throw new RuntimeException('EXP002 remote fixture must not be listed.');
            }
        });
        config(['filesystems.disks.retention_remote' => ['driver' => 's3', 'visibility' => 'private'],
            ($kind === 'export' ? 'exports.disk' : 'database-exports.disk') => 'retention_remote']);

        $successful = $kind === 'export' && $visibility === 'private';
        $this->artisan($this->command($kind))->assertExitCode($successful ? 0 : 1);

        if ($successful) {
            $this->assertNull($record->fresh());
            $this->assertSame([$unrelated => 'EXP002 remote unrelated'], $state->files);
            $this->assertSame(2, count(array_filter($state->calls, fn (array $call): bool => $call[0] === 'delete')));
        } else {
            $this->assertSame($before, $record->fresh()->getRawOriginal());
            $this->assertSame('EXP002 remote referenced', $state->files[$record->file_path]);
            $this->assertSame('EXP002 remote part', $state->files[$record->file_path.'.part']);
            if ($kind === 'dump') {
                $this->assertSame([], $state->calls);
            }
        }
        $this->assertSame([], $state->forbidden);
        $this->assertSame('EXP002 remote unrelated', $state->files[$unrelated]);
        $this->assertIsolated();
    }

    /** @return array<string, array{string, string}> */
    public static function remoteStorageProfiles(): array
    {
        return ['private registered export' => ['export', 'private'], 'public remote export object' => ['export', 'public'],
            'remote dump refused' => ['dump', 'private']];
    }

    #[DataProvider('artifactKinds')]
    public function test_an_uppercase_orphan_namespace_remains_protected_by_its_lowercase_pending_uuid(string $kind): void
    {
        $this->assertSame('Windows', PHP_OS_FAMILY);
        $uuid = $this->caseAliasUuid();
        $this->assertNotSame($uuid, strtoupper($uuid));
        $record = $this->record($kind, ['uuid' => $uuid, 'status' => 'pending', 'file_path' => null,
            'expires_at' => null, 'completed_at' => null]);
        $path = $this->directory($kind).'/'.strtoupper($uuid).'/'.strtoupper($this->caseAliasUuid()).($kind === 'export' ? '.csv' : '.sql');
        $this->putWithMtime($path, now()->subDays(2)->timestamp);
        $before = $record->fresh()->getRawOriginal();
        $absolute = Storage::disk('local')->path($path);
        $digest = hash_file('sha256', $absolute);
        $mtime = filemtime($absolute);

        $this->artisan($this->command($kind))->assertExitCode(0);

        $this->assertSame($before, $record->fresh()->getRawOriginal());
        $this->assertFileExists($absolute);
        $this->assertSame($digest, hash_file('sha256', $absolute));
        $this->assertSame($mtime, filemtime($absolute));
        $this->assertIsolated();
    }

    #[DataProvider('artifactKinds')]
    public function test_a_windows_case_variant_reference_protects_an_orphan_owned_by_another_namespace(string $kind): void
    {
        $this->assertSame('Windows', PHP_OS_FAMILY);
        $uuid = $this->caseAliasUuid();
        $token = $this->caseAliasUuid();
        $extension = $kind === 'export' ? '.csv' : '.sql';
        $path = $this->directory($kind).'/'.$uuid.'/'.$token.$extension;
        $alias = $this->directory($kind).'/'.strtoupper($uuid).'/'.strtoupper($token).$extension;
        $this->assertNotSame($path, $alias);
        $record = $this->record($kind, ['status' => 'pending', 'file_path' => null, 'expires_at' => null, 'completed_at' => null]);
        $this->assertNotSame(strtolower($record->uuid), strtolower($uuid));
        $this->putWithMtime($path, now()->subDays(2)->timestamp);
        $record->getConnection()->table($record->getTable())->where('id', $record->id)->update(['file_path' => $alias]);
        $record->refresh();
        $before = $record->fresh()->getRawOriginal();
        $absolute = Storage::disk('local')->path($path);
        $digest = hash_file('sha256', $absolute);
        $mtime = filemtime($absolute);

        $this->artisan($this->command($kind))->assertExitCode(0);

        $this->assertSame($before, $record->fresh()->getRawOriginal());
        $this->assertFileExists($absolute);
        $this->assertSame($digest, hash_file('sha256', $absolute));
        $this->assertSame($mtime, filemtime($absolute));
        $this->assertIsolated();
    }

    #[DataProvider('artifactKinds')]
    public function test_a_windows_case_variant_foreign_reference_blocks_expired_record_deletion(string $kind): void
    {
        $this->assertSame('Windows', PHP_OS_FAMILY);
        $uuid = $this->caseAliasUuid();
        $path = $this->flatPath($kind, $uuid);
        $alias = $this->flatPath($kind, strtoupper($uuid));
        $this->assertNotSame($path, $alias);
        $expired = $this->record($kind, ['uuid' => $uuid, 'file_path' => $path]);
        $active = $this->record($kind, ['status' => 'pending', 'file_path' => $alias, 'expires_at' => null, 'completed_at' => null]);
        $beforeExpired = $expired->fresh()->getRawOriginal();
        $beforeActive = $active->fresh()->getRawOriginal();
        $absolute = Storage::disk('local')->path($path);
        $digest = hash_file('sha256', $absolute);
        $mtime = filemtime($absolute);

        $this->artisan($this->command($kind))->assertExitCode(1);

        $this->assertSame($beforeExpired, $expired->fresh()->getRawOriginal());
        $this->assertSame($beforeActive, $active->fresh()->getRawOriginal());
        $this->assertFileExists($absolute);
        $this->assertSame($digest, hash_file('sha256', $absolute));
        $this->assertSame($mtime, filemtime($absolute));
        $this->assertIsolated();
    }

    public function test_orphan_cleanup_rechecks_regular_file_identity_after_the_second_reference_callback(): void
    {
        $path = $this->flatPath('export', $this->caseAliasUuid());
        $this->putWithMtime($path, now()->subDays(2)->timestamp);
        $absolute = Storage::disk('local')->path($path);
        $calls = 0;
        $files = new PrivateExportFiles('exports', ['csv', 'tsv', 'xlsx'], true);

        $failures = $files->pruneOrphans(function (string $uuid, string $candidate) use ($path, $absolute, &$calls): bool {
            $this->assertSame($path, $candidate);
            if (++$calls === 2) {
                $bytes = 'EXP002 concurrently renewed regular artifact';
                $this->assertSame(strlen($bytes), file_put_contents($absolute, $bytes));
                $this->assertTrue(touch($absolute, now()->timestamp));
                clearstatcache(true, $absolute);
            }

            return false;
        }, now()->subDay()->toImmutable());

        $this->assertSame(0, $failures);
        $this->assertGreaterThanOrEqual(2, $calls);
        $this->assertSame('EXP002 concurrently renewed regular artifact', file_get_contents($absolute));
        $this->assertSame(now()->timestamp, filemtime($absolute));
        $this->assertIsolated();
    }

    public function test_orphan_cleanup_refuses_a_hardlink_created_by_the_second_reference_callback(): void
    {
        $path = $this->flatPath('export', $this->caseAliasUuid());
        $this->putWithMtime($path, now()->subDays(2)->timestamp);
        $disk = Storage::disk('local');
        $absolute = $disk->path($path);
        (new Filesystem)->ensureDirectoryExists($disk->path('sentinels'));
        $linked = $disk->path('sentinels/orphan-race-hardlink.bin');
        $calls = 0;
        $files = new PrivateExportFiles('exports', ['csv', 'tsv', 'xlsx'], true);

        $failures = $files->pruneOrphans(function (string $uuid, string $candidate) use ($path, $absolute, $linked, &$calls): bool {
            $this->assertSame($path, $candidate);
            if (++$calls === 2) {
                $this->assertTrue(link($absolute, $linked));
                clearstatcache(true, $absolute);
                clearstatcache(true, $linked);
            }

            return false;
        }, now()->subDay()->toImmutable());

        $this->assertSame(1, $failures);
        $this->assertGreaterThanOrEqual(2, $calls);
        $this->assertSame('EXP002 filesystem sentinel', file_get_contents($absolute));
        $this->assertSame('EXP002 filesystem sentinel', file_get_contents($linked));
        $this->assertSame(2, lstat($absolute)['nlink']);
        $this->assertIsolated();
    }

    private function caseAliasUuid(): string
    {
        return 'a'.substr((string) Str::uuid(), 1);
    }

    private function putWithMtime(string $path, int $timestamp): void
    {
        $disk = Storage::disk('local');
        $this->assertTrue($disk->put($path, 'EXP002 filesystem sentinel'));
        $this->assertTrue(touch($disk->path($path), $timestamp));
        clearstatcache(true, $disk->path($path));
        $this->assertSame($timestamp, filemtime($disk->path($path)));
    }

    /** @return array<string, array{string}> */
    public static function artifactKinds(): array
    {
        return ['spreadsheet exports' => ['export'], 'database dumps' => ['dump']];
    }

    /** @param array<string, mixed> $attributes */
    private function record(string $kind, array $attributes = []): Export|DatabaseExport
    {
        $uuid = (string) Str::uuid();
        $path = $this->flatPath($kind, $uuid);
        $defaults = ['uuid' => $uuid, 'user_id' => $this->owner->id, 'status' => 'completed', 'file_path' => $path,
            'file_name' => basename($path), 'file_size' => 22, 'completed_at' => now()->subHours(2), 'expires_at' => now()->subHour()];
        $record = $kind === 'export'
            ? Export::factory()->create([...$defaults, ...$attributes])
            : DatabaseExport::query()->create(['export_type' => 'full', ...$defaults, ...$attributes]);
        if (is_string($record->file_path)) {
            Storage::disk('local')->put($record->file_path, 'EXP002 private fixture');
        }

        return $record;
    }

    private function flatPath(string $kind, string $uuid): string
    {
        return $this->directory($kind).'/'.$uuid.($kind === 'export' ? '.csv' : '.sql');
    }

    private function directory(string $kind): string
    {
        return $kind === 'export' ? 'exports' : 'database-exports';
    }

    private function command(string $kind): string
    {
        return $kind === 'export' ? 'exports:prune' : 'database-exports:prune';
    }

    private function failDelete(string $target, string $failure): void
    {
        $base = Storage::disk('local');
        Storage::set('local', new class($base, $target, $failure) extends FilesystemAdapter
        {
            public function __construct(FilesystemAdapter $base, private readonly string $target, private readonly string $failure)
            {
                parent::__construct($base->getDriver(), $base->getAdapter(), $base->getConfig());
            }

            public function delete($paths): bool
            {
                if (in_array($this->target, (array) $paths, true)) {
                    if ($this->failure === 'throw') {
                        throw new RuntimeException('EXP002 controlled private deletion failure');
                    }

                    return false;
                }

                return parent::delete($paths);
            }
        });
    }

    private function assertIsolated(): void
    {
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }
}
