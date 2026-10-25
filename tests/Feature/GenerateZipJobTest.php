<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Uploads\Jobs\GenerateZipJob;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Models\ZipRequest;
use Closure;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use Tests\TestCase;
use ZipArchive;

class GenerateZipJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
        config(['filesystems.disks.zip_source' => ['driver' => 'local', 'root' => $this->testEnvironment->path.'/zip-source', 'throw' => true],
            'uploads.zip.processing_timeout' => 60, 'uploads.zip.pending_timeout' => 120, 'uploads.zip.retention_hours' => 2]);
        config(['filesystems.disks.local' => ['driver' => 'local', 'root' => $this->testEnvironment->path.'/z', 'throw' => true, 'visibility' => 'private']]);
        Storage::forgetDisk('local');
        Storage::fake('zip_source');
        Notification::fake();
        Mail::fake();
        Queue::fake();
        Http::preventStrayRequests();
        $this->travelTo(now()->startOfSecond());
    }

    public function test_zip_is_materialized_on_the_configured_private_disk_with_each_member_once(): void
    {
        config(['uploads.zip.retention_hours' => 3]);
        [$owner, $uploads, $request] = $this->fixture();
        $observer = $this->observeSource();
        $job = unserialize(serialize(new GenerateZipJob($request)));
        $this->assertInstanceOf(GenerateZipJob::class, $job);

        $job->handle();

        $request->refresh();
        $this->assertSame('ready', $request->status);
        $this->assertCanonicalPath($request);
        $this->assertSame(now()->addHours(3)->getTimestamp(), $request->expires_at->getTimestamp());
        $this->assertSame(['report.txt' => "ZIP001 first\n", 'report (2).txt' => "ZIP001 second\n"], $this->archiveEntries($request->zip_path));
        $this->assertSame([$request->zip_path], Storage::disk('local')->allFiles('zip-requests/'.$request->uuid));
        $this->assertSame(2, $observer->reads);
        $this->assertClosedStreams($observer);
        $this->assertSourceBytes($uploads);
        $this->assertTrue(Model::preventsLazyLoading());
        $this->assertIsolated();
    }

    #[DataProvider('terminalRedeliveries')]
    public function test_redelivery_never_rewrites_an_already_claimed_or_terminal_request(string $status): void
    {
        [, $uploads, $request] = $this->fixture(['status' => $status]);
        $path = $this->ownedPath($request);
        $request->update(['zip_path' => $path, 'expires_at' => now()->addHour()]);
        Storage::disk('local')->put($path, 'ZIP001 existing artifact sentinel');
        touch(Storage::disk('local')->path($path), now()->subHour()->getTimestamp());
        $before = $request->fresh()->getRawOriginal();
        clearstatcache(true, Storage::disk('local')->path($path));
        $mtime = filemtime(Storage::disk('local')->path($path));
        $observer = $this->observeSource();

        (new GenerateZipJob($request))->handle();

        $this->assertSame($before, $request->fresh()->getRawOriginal());
        $this->assertSame('ZIP001 existing artifact sentinel', Storage::disk('local')->get($path));
        clearstatcache(true, Storage::disk('local')->path($path));
        $this->assertSame($mtime, filemtime(Storage::disk('local')->path($path)));
        $this->assertSame(0, $observer->reads);
        $this->assertSourceBytes($uploads);
        $this->assertIsolated();
    }

    /** @return array<string, array{string}> */
    public static function terminalRedeliveries(): array
    {
        return ['processing' => ['processing'], 'ready' => ['ready'], 'failed' => ['failed']];
    }

    #[DataProvider('sourceFailures')]
    public function test_input_failure_persists_generic_failure_and_removes_partial_files(string $failure): void
    {
        [, $uploads, $request] = $this->fixture();
        if ($failure === 'missing') {
            Storage::disk('zip_source')->delete($uploads[1]->path);
        }
        $observer = $this->observeSource($failure === 'missing' ? null : $failure, 2);

        (new GenerateZipJob($request))->handle();

        $this->assertFailedAndClean($request);
        $this->assertClosedStreams($observer);
        $this->assertSame("ZIP001 first\n", Storage::disk('zip_source')->get($uploads[0]->path));
        if ($failure !== 'missing') {
            $this->assertSourceBytes($uploads);
        }
        $this->assertIsolated();
    }

    /** @return array<string, array{string}> */
    public static function sourceFailures(): array
    {
        return ['null stream' => ['null'], 'stream throws after first member' => ['throw'], 'truncated stream' => ['short'], 'selected member missing' => ['missing']];
    }

    public function test_staging_write_false_fails_and_closes_the_source_stream(): void
    {
        [, $uploads, $request] = $this->fixture();
        $source = $this->observeSource();
        $disk = $this->observeOutput('put-false');

        (new GenerateZipJob($request))->handle();

        $this->assertFailedAndClean($request);
        $this->assertGreaterThan(0, $disk->writes);
        $this->assertClosedStreams($source);
        $this->assertSourceBytes($uploads);
    }

    public function test_archive_close_false_cannot_publish_a_ready_file(): void
    {
        [, $uploads, $request] = $this->fixture();
        $this->observeSource();
        $writer = new class extends ZipArchive
        {
            private bool $closed = false;

            public function close(): bool
            {
                if (! $this->closed) {
                    parent::close();
                    $this->closed = true;
                }

                return false;
            }
        };
        $this->app->bind(ZipArchive::class, fn (): ZipArchive => $writer);

        (new GenerateZipJob($request))->handle();

        $this->assertFailedAndClean($request);
        $this->assertSourceBytes($uploads);
    }

    #[DataProvider('ineligibleOwners')]
    /** @return array<string, array{string}> */
    public static function ineligibleOwners(): array
    {
        return ['permission revoked' => ['permission'], 'unverified' => ['unverified'], 'forced password change' => ['password'], 'one parent missing' => ['parent']];
    }

    public function test_a_second_delivery_during_generation_cannot_claim_or_write_the_request(): void
    {
        [, , $request] = $this->fixture();
        $second = new GenerateZipJob($request->fresh());
        $nested = false;
        $observer = $this->observeSource(hook: function () use ($request, $second, &$nested): void {
            if ($nested) {
                return;
            }
            $nested = true;
            $before = $request->fresh()->getRawOriginal();
            $second->handle();
            $this->assertSame($before, $request->fresh()->getRawOriginal());
        });

        (new GenerateZipJob($request))->handle();

        $this->assertTrue($nested);
        $this->assertSame('ready', $request->fresh()->status);
        $this->assertSame(2, $observer->reads);
        $this->assertCount(2, $this->archiveEntries($request->fresh()->zip_path));
    }

    public function test_timeout_callback_fails_only_its_claim_and_cleans_its_attempt(): void
    {
        [, , $request] = $this->fixture();
        $job = new GenerateZipJob($request);
        $timedOut = false;
        $this->observeSource(hook: function () use ($job, &$timedOut): void {
            if (! $timedOut) {
                $timedOut = true;
                $job->failed(new TimeoutExceededException('ZIP001_PRIVATE_SENTINEL controlled timeout'));
            }
        });

        $job->handle();

        $this->assertTrue($timedOut);
        $this->assertFailedAndClean($request);
        $job->failed(new TimeoutExceededException('ZIP001 repeated timeout'));
        $this->assertFailedAndClean($request);
    }

    public function test_a_late_producer_cannot_publish_after_its_processing_deadline(): void
    {
        [, , $request] = $this->fixture();
        $advanced = false;
        $this->observeSource(hook: function () use (&$advanced): void {
            if (! $advanced) {
                $advanced = true;
                $this->travel(61)->seconds();
            }
        });

        (new GenerateZipJob($request))->handle();

        $this->assertTrue($advanced);
        $this->assertFailedAndClean($request);
    }

    public function test_lost_attempt_fence_cannot_overwrite_another_attempt_or_terminal_state(): void
    {
        [, , $request] = $this->fixture();
        $otherPath = $this->ownedPath($request);
        Storage::disk('local')->put($otherPath, 'ZIP001 other attempt sentinel');
        $reconciled = false;
        $this->observeSource(hook: function () use ($request, $otherPath, &$reconciled): void {
            if (! $reconciled) {
                $reconciled = true;
                ZipRequest::whereKey($request->id)->update(['status' => 'failed', 'zip_path' => $otherPath, 'error_message' => 'ZIP001 reconciled failure']);
            }
        });

        (new GenerateZipJob($request))->handle();

        $this->assertTrue($reconciled);
        $this->assertSame('failed', $request->fresh()->status);
        $this->assertSame($otherPath, $request->fresh()->zip_path);
        $this->assertSame('ZIP001 other attempt sentinel', Storage::disk('local')->get($otherPath));
        $this->assertSame([$otherPath], Storage::disk('local')->allFiles('zip-requests/'.$request->uuid));
    }

    public function test_recovery_creates_a_new_request_without_reopening_the_failed_request(): void
    {
        [$owner, $uploads, $failed] = $this->fixture(['status' => 'failed', 'error_message' => 'ZIP001 previous failure']);
        $before = $failed->fresh()->getRawOriginal();
        Bus::fake([GenerateZipJob::class]);

        $response = $this->actingAs($owner, 'web')->postJson(route('api.uploads.zip-requests.store'), ['ids' => array_map(fn (Uploaded $upload): int => $upload->id, $uploads), 'label' => 'ZIP001 retry'])->assertAccepted();
        $this->assertNotSame($failed->uuid, $response->json('data.uuid'));
        $this->assertSame('pending', $response->json('data.status'));
        $this->assertSame($before, $failed->fresh()->getRawOriginal());
        Bus::assertDispatched(GenerateZipJob::class, 1);
    }

    public function test_owner_downloads_only_the_materialized_snapshot_and_other_users_cannot_probe_it(): void
    {
        [$owner, $uploads, $request] = $this->fixture();
        $this->observeSource();
        (new GenerateZipJob($request))->handle();
        $request->refresh();
        $this->assertSame('ready', $request->status);
        $this->assertCanonicalPath($request);
        $bytes = Storage::disk('local')->get($request->zip_path);
        $owner->permissions()->detach();
        Storage::disk('zip_source')->delete(array_map(fn (Uploaded $upload): string => $upload->path, $uploads));
        Bus::fake([GenerateZipJob::class]);

        $this->actingAs($owner, 'web')->getJson(route('api.uploads.zip-requests.show', $request->uuid))->assertOk()->assertJsonPath('data.status', 'ready');
        $this->get(route('api.uploads.zip-requests.download', $request->uuid))->assertOk()->assertDownload('zip001-download.zip')->assertStreamedContent($bytes);
        $other = User::factory()->create();
        Storage::shouldReceive('disk')->never();
        $this->app->make('auth')->forgetGuards();
        $this->actingAs($other, 'web')->getJson(route('api.uploads.zip-requests.show', $request->uuid))->assertNotFound();
        $this->getJson(route('api.uploads.zip-requests.download', $request->uuid))->assertNotFound();
        Bus::assertNotDispatched(GenerateZipJob::class);
    }

    public function test_ready_record_with_missing_artifact_never_reconstructs_on_download(): void
    {
        [$owner, , $request] = $this->fixture(['status' => 'ready', 'expires_at' => now()->addHour()]);
        $request->update(['zip_path' => $this->ownedPath($request)]);
        $observer = $this->observeSource();
        Bus::fake([GenerateZipJob::class]);

        $this->actingAs($owner, 'web')->getJson(route('api.uploads.zip-requests.download', $request->uuid))->assertNotFound();

        $this->assertSame(0, $observer->reads);
        $this->assertNotSame('pending', $request->fresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles('zip-requests/'.$request->uuid));
        $this->assertFileDoesNotExist(storage_path('app/private/zip-requests/'.$request->uuid.'.zip'));
        Bus::assertNotDispatched(GenerateZipJob::class);
    }

    public function test_expiration_and_legacy_ttl_never_renew_by_status_queries_or_serve_bytes(): void
    {
        [$owner, , $request] = $this->fixture(['status' => 'ready', 'expires_at' => null]);
        $path = $this->ownedPath($request);
        $request->update(['zip_path' => $path]);
        ZipRequest::whereKey($request->id)->update(['updated_at' => now()->subHours(3)]);
        Storage::disk('local')->put($path, 'ZIP001 expired legacy artifact');
        $this->actingAs($owner, 'web')->getJson(route('api.uploads.zip-requests.show', $request->uuid))->assertOk()->assertJsonPath('data.download_url', null);
        $this->getJson(route('api.uploads.zip-requests.download', $request->uuid))->assertStatus(422);
        $this->assertSame('failed', $request->fresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles('zip-requests/'.$request->uuid));
    }

    public function test_prune_reconciles_abandoned_requests_and_removes_only_owned_expired_artifacts(): void
    {
        [, $uploads, $expired] = $this->fixture(['status' => 'ready', 'expires_at' => now()->subSecond()]);
        $expired->update(['zip_path' => $this->ownedPath($expired)]);
        Storage::disk('local')->put($expired->zip_path, 'ZIP001 expired');
        $processing = $expired->replicate();
        $processing->fill(['uuid' => (string) Str::uuid(), 'status' => 'processing', 'expires_at' => now()->subSecond()])->save();
        $processing->update(['zip_path' => $this->ownedPath($processing)]);
        Storage::disk('local')->put(dirname($processing->zip_path).'/archive.zip.part', 'ZIP001 abandoned partial');
        $pending = $expired->replicate();
        $pending->fill(['uuid' => (string) Str::uuid(), 'status' => 'pending', 'zip_path' => null, 'expires_at' => null])->save();
        ZipRequest::whereKey($pending->id)->update(['updated_at' => now()->subSeconds(121)]);
        $active = $expired->replicate();
        $active->fill(['uuid' => (string) Str::uuid(), 'status' => 'ready', 'expires_at' => now()->addHour()])->save();
        $active->update(['zip_path' => $this->ownedPath($active)]);
        Storage::disk('local')->put($active->zip_path, 'ZIP001 unexpired');
        Storage::disk('local')->put('sentinels/keep.txt', 'ZIP001 private sentinel');

        $this->artisan('uploads:prune-zips')->assertSuccessful();
        $this->artisan('uploads:prune-zips')->assertSuccessful();

        foreach ([$expired, $processing, $pending] as $request) {
            $this->assertSame('failed', $request->fresh()->status);
            $this->assertModelExists($request);
            $this->assertSame([], Storage::disk('local')->allFiles('zip-requests/'.$request->uuid));
        }
        $this->assertSame('ready', $active->fresh()->status);
        $this->assertSame('ZIP001 unexpired', Storage::disk('local')->get($active->zip_path));
        $this->assertSame('ZIP001 private sentinel', Storage::disk('local')->get('sentinels/keep.txt'));
        $this->assertSourceBytes($uploads);
    }

    #[DataProvider('deleteFailures')]
    public function test_failed_cleanup_keeps_the_residue_identifiable_and_the_command_reports_failure(string $failure): void
    {
        [, , $request] = $this->fixture(['status' => 'failed']);
        $path = $this->ownedPath($request);
        $request->update(['zip_path' => $path]);
        Storage::disk('local')->put($path, 'ZIP001 retained residue');
        $base = Storage::disk('local');
        $this->observeOutput($failure);

        $this->artisan('uploads:prune-zips')->assertFailed();

        $this->assertSame('failed', $request->fresh()->status);
        $this->assertSame($path, $request->fresh()->zip_path);
        $this->assertSame('ZIP001 retained residue', $base->get($path));
        Storage::set('local', $base);
        $this->artisan('uploads:prune-zips')->assertSuccessful();
        $this->assertNull($request->fresh()->zip_path);
        $this->assertSame([], $base->allFiles('zip-requests/'.$request->uuid));
    }

    /** @return array<string, array{string}> */
    public static function deleteFailures(): array
    {
        return ['false' => ['delete-false'], 'exception' => ['delete-throw']];
    }

    public function test_legacy_sources_with_unknown_size_can_be_materialized(): void
    {
        [, $uploads, $request] = $this->fixture();
        foreach ($uploads as $upload) {
            $upload->update(['size' => null]);
        }
        (new GenerateZipJob($request))->handle();
        $request->refresh();
        $this->assertSame('ready', $request->status);
        $this->assertSame(['report.txt' => "ZIP001 first\n", 'report (2).txt' => "ZIP001 second\n"], $this->archiveEntries($request->zip_path));
        $this->assertSourceBytes($uploads);
    }

    public function test_abandoned_pending_request_cannot_be_claimed_after_its_deadline(): void
    {
        [, , $request] = $this->fixture();
        ZipRequest::whereKey($request->id)->update(['updated_at' => now()->subSeconds(121)]);
        $observer = $this->observeSource();
        (new GenerateZipJob($request))->handle();
        $this->assertFailedAndClean($request);
        $this->assertSame(0, $observer->reads);
    }

    public function test_status_reconciles_ready_metadata_with_an_invalid_artifact_path(): void
    {
        [$owner, , $request] = $this->fixture(['status' => 'ready', 'expires_at' => now()->addHour(), 'zip_path' => '../outside.zip']);
        $this->actingAs($owner, 'web')->getJson(route('api.uploads.zip-requests.show', $request->uuid))
            ->assertOk()->assertJsonPath('data.status', 'failed')->assertJsonPath('data.download_url', null)
            ->assertJsonPath('data.error_message', ZipRequest::FAILURE_MESSAGE);
        $this->assertSame('../outside.zip', $request->fresh()->zip_path);
    }

    public function test_private_path_guards_refuse_public_roots_and_foreign_namespaces(): void
    {
        [, , $request] = $this->fixture();
        $path = $this->ownedPath($request);
        $this->assertNotNull($request->resolveArtifactPath($path));
        foreach (['../outside.zip', 'zip-requests/'.Str::uuid().'/'.Str::uuid().'/archive.zip', $this->testEnvironment->path.'/outside.zip'] as $foreign) {
            $this->assertNull($request->resolveArtifactPath($foreign));
        }
        config(['filesystems.disks.public.root' => Storage::disk('local')->path('zip-requests')]);
        $this->assertNull($request->resolveArtifactPath($path));
    }

    #[DataProvider('unsafeOutputDisks')]
    public function test_output_disk_must_have_a_private_local_driver_and_adapter(string $driver, string $visibility, bool $localAdapter): void
    {
        [, , $request] = $this->fixture();
        $base = Storage::disk('local');
        $adapter = $localAdapter ? $base->getAdapter() : \Mockery::mock(\League\Flysystem\FilesystemAdapter::class);
        Storage::set('local', new FilesystemAdapter($base->getDriver(), $adapter, array_replace($base->getConfig(), ['driver' => $driver, 'visibility' => $visibility])));
        $this->assertNull($request->resolveArtifactPath($this->ownedPath($request)));
    }

    /** @return array<string, array{string, string, bool}> */
    public static function unsafeOutputDisks(): array
    {
        return ['nonlocal configuration' => ['ftp', 'private', true], 'public visibility' => ['local', 'public', true], 'nonlocal adapter' => ['local', 'private', false]];
    }

    public function test_cleanup_refuses_a_hard_link_and_preserves_the_external_sentinel(): void
    {
        [, , $request] = $this->fixture(['status' => 'failed']);
        $path = $this->ownedPath($request);
        $request->update(['zip_path' => $path]);
        Storage::disk('local')->put($path, 'ZIP001 ordinary archive');
        $sentinel = $this->testEnvironment->path.'/keep.txt';
        file_put_contents($sentinel, 'ZIP001 linked sentinel');
        $link = dirname(Storage::disk('local')->path($path)).'/linked.tmp';
        $this->assertTrue(link($sentinel, $link));
        try {
            $this->assertFalse($request->cleanupArtifacts());
            $this->assertSame($path, $request->fresh()->zip_path);
            $this->assertSame('ZIP001 linked sentinel', file_get_contents($sentinel));
            $this->assertFileExists($link);
        } finally {
            unlink($link);
        }
        $this->assertTrue($request->cleanupArtifacts());
        $this->assertSame('ZIP001 linked sentinel', file_get_contents($sentinel));
    }

    public function test_zip_retention_schedule_is_hourly_and_non_overlapping(): void
    {
        $events = array_values(array_filter(app(Schedule::class)->events(),
            fn ($event): bool => str_contains($event->command ?? '', 'uploads:prune-zips')));
        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    /** @param array<string, mixed> $attributes
     * @return array{User, list<Uploaded>, ZipRequest}
     */
    private function fixture(array $attributes = []): array
    {
        $owner = User::factory()->create(['must_change_password' => false]);
        $owner->permissions()->attach(Permission::firstOrCreate(['slug' => 'view-clients'], ['name' => 'View clients', 'group' => 'clients']));
        $parent = Client::factory()->create();
        $uploads = [];
        foreach (["ZIP001 first\n", "ZIP001 second\n"] as $index => $bytes) {
            $path = 'source/'.$parent->uuid.'/file-'.$index.'.txt';
            Storage::disk('zip_source')->put($path, $bytes);
            $uploads[] = Uploaded::query()->create(['user_id' => $owner->id, 'uploader_id' => $owner->id,
                'parentable_type' => Client::class, 'parentable_id' => $parent->id, 'name' => 'ZIP001 member '.$index,
                'file_name' => 'file-'.$index.'.txt', 'original_name' => 'report.txt', 'mime_type' => 'text/plain',
                'path' => $path, 'extension' => 'txt', 'disk' => 'zip_source', 'size' => strlen($bytes)]);
        }
        $request = ZipRequest::query()->create(['uuid' => (string) Str::uuid(), 'user_id' => $owner->id, 'label' => 'ZIP001 download',
            'file_ids' => array_map(fn (Uploaded $upload): int => $upload->id, $uploads), 'file_count' => 2, 'status' => 'pending', ...$attributes]);

        return [$owner, $uploads, $request];
    }

    private function ownedPath(ZipRequest $request): string
    {
        return 'zip-requests/'.$request->uuid.'/'.Str::uuid().'/archive.zip';
    }

    private function assertCanonicalPath(ZipRequest $request): void
    {
        $this->assertMatchesRegularExpression('~\Azip-requests/'.preg_quote($request->uuid, '~').'/[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}/archive\.zip\z~', $request->zip_path);
        Storage::disk('local')->assertExists($request->zip_path);
    }

    /** @return array<string, string> */
    private function archiveEntries(string $path): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($path), ZipArchive::CHECKCONS));
        try {
            $this->assertSame(2, $zip->numFiles);
            $entries = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                $this->assertIsString($name);
                $this->assertStringNotContainsString('/', $name);
                $this->assertArrayNotHasKey($name, $entries);
                $bytes = $zip->getFromIndex($index);
                $this->assertIsString($bytes);
                $entries[$name] = $bytes;
            }

            return $entries;
        } finally {
            $this->assertTrue($zip->close());
        }
    }

    private function assertFailedAndClean(ZipRequest $request): void
    {
        $request->refresh();
        $this->assertSame('failed', $request->status);
        $this->assertIsString($request->error_message);
        $this->assertNotSame('', $request->error_message);
        $this->assertStringNotContainsString('ZIP001_PRIVATE_SENTINEL', $request->error_message);
        $this->assertNull($request->zip_path);
        $this->assertSame([], Storage::disk('local')->allFiles('zip-requests/'.$request->uuid));
        $this->assertFileDoesNotExist(storage_path('app/private/zip-requests/'.$request->uuid.'.zip'));
        $legacyTmp = storage_path('app/private/zip-requests/tmp');
        if (is_dir($legacyTmp)) {
            $this->assertSame([], (new Filesystem)->files($legacyTmp));
        }
    }

    /** @param list<Uploaded> $uploads */
    private function assertSourceBytes(array $uploads): void
    {
        foreach ($uploads as $index => $upload) {
            $this->assertSame($index === 0 ? "ZIP001 first\n" : "ZIP001 second\n", Storage::disk('zip_source')->get($upload->path));
        }
    }

    private function assertClosedStreams(stdClass $observer): void
    {
        foreach ($observer->streams as $stream) {
            $this->assertFalse(is_resource($stream), 'Every opened source stream must be closed.');
        }
    }

    private function assertIsolated(): void
    {
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    private function observeSource(?string $failure = null, int $at = 1, ?Closure $hook = null): stdClass
    {
        $base = Storage::disk('zip_source');
        $observer = (object) ['reads' => 0, 'streams' => []];
        Storage::set('zip_source', new class($base, $observer, $failure, $at, $hook) extends FilesystemAdapter
        {
            public function __construct(FilesystemAdapter $base, private readonly stdClass $observer, private readonly ?string $failure, private readonly int $at, private readonly ?Closure $hook)
            {
                parent::__construct($base->getDriver(), $base->getAdapter(), $base->getConfig());
            }

            public function path($path): string
            {
                throw new RuntimeException('ZIP001 remote fixture has no local path.');
            }

            public function readStream($path): mixed
            {
                $this->observer->reads++;
                ($this->hook)?->__invoke();
                if ($this->observer->reads === $this->at) {
                    if ($this->failure === 'null') {
                        return null;
                    }
                    if ($this->failure === 'throw') {
                        throw new RuntimeException('ZIP001_PRIVATE_SENTINEL synthetic remote read failure');
                    }
                }
                if ($this->failure === 'short' && $this->observer->reads === $this->at) {
                    $stream = fopen('php://temp', 'w+b');
                    fwrite($stream, 'ZIP');
                    rewind($stream);
                } else {
                    $stream = parent::readStream($path);
                }
                if (is_resource($stream)) {
                    $this->observer->streams[] = $stream;
                }

                return $stream;
            }
        });

        return $observer;
    }

    private function observeOutput(string $failure): stdClass
    {
        $base = Storage::disk('local');
        $observer = (object) ['writes' => 0];
        Storage::set('local', new class($base, $observer, $failure) extends FilesystemAdapter
        {
            public function __construct(FilesystemAdapter $base, private readonly stdClass $observer, private readonly string $failure)
            {
                parent::__construct($base->getDriver(), $base->getAdapter(), $base->getConfig());
            }

            public function put($path, $contents, $options = []): bool
            {
                $this->observer->writes++;
                if ($this->failure === 'put-false') {
                    return false;
                }

                return parent::put($path, $contents, $options);
            }

            public function delete($paths): bool
            {
                if ($this->failure === 'delete-false') {
                    return false;
                }
                if ($this->failure === 'delete-throw') {
                    throw new RuntimeException('ZIP001_PRIVATE_SENTINEL cleanup refusal');
                }

                return parent::delete($paths);
            }

            public function deleteDirectory($directory): bool
            {
                if ($this->failure === 'delete-false') {
                    return false;
                }
                if ($this->failure === 'delete-throw') {
                    throw new RuntimeException('ZIP001_PRIVATE_SENTINEL cleanup refusal');
                }

                return parent::deleteDirectory($directory);
            }
        });

        return $observer;
    }
}
