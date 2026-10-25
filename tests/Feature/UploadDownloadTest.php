<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Uploads\Jobs\GenerateZipJob;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Models\ZipRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use Tests\TestCase;

class UploadDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_list_client_uploads(): void
    {
        $user = User::factory()->create();
        $this->grantClientPermission($user, 'view-clients');
        $client = Client::factory()->create();
        $upload = $this->createUploadFor($user, $client);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/uploads?'.http_build_query([
                'parentable_type' => 'client',
                'parentable_id' => $client->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $upload->id)
            ->assertJsonPath('data.0.download_url', route('uploads.download', ['upload' => $upload]))
            ->assertJsonPath('data.0.preview_url', route('uploads.preview', ['upload' => $upload]));
    }

    public function test_multirow_old_upload_listing_resolves_permissions_with_lazy_loading_prevented(): void
    {
        $user = User::factory()->create();
        $this->grantClientPermission($user, 'view-clients');
        $client = Client::factory()->create();
        $first = $this->createUploadFor($user, $client);
        $second = $first->replicate();
        $second->forceFill([
            'name' => 'Second OLD attachment',
            'original_name' => 'second.pdf',
            'file_name' => 'second.pdf',
            'path' => 'uploads/client/'.$client->uuid.'/second.pdf',
            'mime_type' => 'text/html',
        ])->save();
        $second->refresh();
        Storage::disk('local')->put($second->path, '<!DOCTYPE html><p>OLD attachment</p>');
        $before = [$first->getAttributes(), $second->getAttributes()];

        $this->assertTrue(Model::preventsLazyLoading());
        $this->withoutExceptionHandling();
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/uploads?'.http_build_query([
                'parentable_type' => 'client',
                'parentable_id' => $client->id,
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $response->json('data.*.id'));
        foreach ($response->json('data') as $item) {
            $this->assertSame(route('uploads.preview', ['upload' => $item['id']]), $item['preview_url']);
            $this->assertSame(route('uploads.download', ['upload' => $item['id']]), $item['download_url']);
            $this->assertTrue($item['permissions']['can_update']);
            $this->assertTrue($item['permissions']['can_delete']);
        }
        $this->assertTrue(Model::preventsLazyLoading());
        $this->assertSame($before, [$first->fresh()->getAttributes(), $second->fresh()->getAttributes()]);
    }

    public function test_authorized_user_can_upload_small_file_for_client(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $this->grantClientPermission($user, 'update-clients');
        $client = Client::factory()->create([
            'uuid' => '6466cc5c-f28c-4491-9c6c-80c27efe6114',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/uploads', [
                'parentable_type' => 'client',
                'parentable_id' => $client->id,
                'tag' => 'document',
                'files' => [UploadedFile::fake()->createWithContent('contract.pdf', $this->fixtureBytes('pdf'))],
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.original_name', 'contract.pdf');

        $upload = Uploaded::query()->latest('id')->firstOrFail();

        $this->assertSame($user->id, $upload->user_id);
        $this->assertSame(Client::class, $upload->parentable_type);
        $this->assertSame($client->id, $upload->parentable_id);
        $this->assertStringStartsWith('uploads/client/6466cc5c-f28c-4491-9c6c-80c27efe6114/', $upload->path);
        Storage::disk('local')->assertExists($upload->path);
    }

    #[DataProvider('invalidUtf8UploadNames')]
    public function test_upload_rejects_invalid_utf8_original_names_before_writing_and_recovers(
        bool $validFileFirst,
        string $errorKey,
    ): void {
        $user = User::factory()->create();
        $this->grantClientPermission($user, 'update-clients');
        $client = Client::factory()->create();
        Storage::fake('local');
        $disk = Storage::disk('local');
        $sentinelPath = 'sentinels/utf8-upload.txt';
        $sentinelBytes = 'Existing upload storage must remain unchanged.';
        $this->assertTrue($disk->put($sentinelPath, $sentinelBytes));
        $beforePaths = $disk->allFiles();
        $beforeHash = hash('sha256', $disk->get($sentinelPath));
        $pdfBytes = $this->fixtureBytes('pdf');
        $files = [];
        if ($validFileFirst) {
            $files[] = UploadedFile::fake()->createWithContent('valid-first.pdf', $pdfBytes);
        }
        $files[] = UploadedFile::fake()->createWithContent("contract.p\xFFdf", $pdfBytes);
        $this->assertSame(0, Uploaded::query()->count());

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/uploads', [
                'parentable_type' => 'client',
                'parentable_id' => $client->id,
                'tag' => 'document',
                'files' => $files,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorKey);

        $this->assertSame(0, Uploaded::query()->count());
        $this->assertSame($beforePaths, $disk->allFiles());
        $this->assertSame($sentinelBytes, $disk->get($sentinelPath));
        $this->assertSame($beforeHash, hash('sha256', $disk->get($sentinelPath)));

        $validName = 'Contrato Café ç.pdf';
        $this->postJson('/api/v1/uploads', [
            'parentable_type' => 'client',
            'parentable_id' => $client->id,
            'tag' => 'document',
            'files' => [UploadedFile::fake()->createWithContent($validName, $pdfBytes)],
        ])
            ->assertCreated()
            ->assertJsonPath('data.0.original_name', $validName);

        $this->assertSame(1, Uploaded::query()->count());
        $upload = Uploaded::query()->firstOrFail();
        $this->assertSame($user->id, $upload->user_id);
        $this->assertSame(Client::class, $upload->parentable_type);
        $this->assertSame($client->id, $upload->parentable_id);
        $this->assertSame($validName, $upload->original_name);
        $this->assertStringStartsWith('uploads/client/'.$client->uuid.'/', $upload->path);
        $this->assertSame($pdfBytes, $disk->get($upload->path));
        $this->assertEqualsCanonicalizing([$sentinelPath, $upload->path], $disk->allFiles());
        $this->assertSame($sentinelBytes, $disk->get($sentinelPath));
        $this->assertSame($beforeHash, hash('sha256', $disk->get($sentinelPath)));
    }

    /** @return array<string, array{bool, string}> */
    public static function invalidUtf8UploadNames(): array
    {
        return [
            'invalid original extension alone' => [false, 'files.0'],
            'valid file before invalid original extension' => [true, 'files.1'],
        ];
    }

    public function test_upload_rejects_unknown_alias(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/uploads', [
                'parentable_type' => 'product',
                'parentable_id' => 1,
                'files' => [UploadedFile::fake()->createWithContent('contract.pdf', $this->fixtureBytes('pdf'))],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parentable_type');
    }

    public function test_preview_and_download_are_protected(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->create();
        $upload = $this->createUploadFor($owner, $client);

        $this->get(route('uploads.download', ['upload' => $upload]))
            ->assertRedirect(route('login'));

        $this->get(route('uploads.preview', ['upload' => $upload]))
            ->assertForbidden();
    }

    public function test_authorized_user_can_preview_and_download_upload(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $upload = $this->createUploadFor($user, $client);

        $this->actingAs($user)
            ->get(route('uploads.preview', ['upload' => $upload]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('uploads.download', ['upload' => $upload]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_authorized_user_can_delete_upload(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $upload = $this->createUploadFor($user, $client);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/uploads/{$upload->id}")
            ->assertOk();

        $this->assertSoftDeleted($upload);
        Storage::disk('local')->assertMissing($upload->path);
    }

    public function test_upload_respects_configured_size_limit(): void
    {
        config()->set('uploads.max_size_mb', 1);

        $user = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/uploads', [
                'parentable_type' => 'client',
                'parentable_id' => $client->id,
                'files' => [UploadedFile::fake()->create('large.pdf', 1025, 'application/pdf')],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');
    }

    public function test_authorized_user_can_request_zip_for_uploads(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $client = Client::factory()->create();
        $upload = $this->createUploadFor($user, $client);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/uploads/zip-requests', [
                'ids' => [$upload->id],
                'label' => 'Client docs',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.label', 'Client docs')
            ->assertJsonPath('data.file_count', 1)
            ->assertJsonPath('data.status', 'pending');

        $zip = ZipRequest::query()->latest('id')->firstOrFail();

        $this->assertSame($user->id, $zip->user_id);
        $this->assertSame([$upload->id], $zip->file_ids);

        Bus::assertDispatched(GenerateZipJob::class);
    }

    /** @return array<string, array{string, string, string}> */
    public static function activeFiles(): array
    {
        $html = '<!DOCTYPE html><html><script>window.__sec011Executed=1</script><body>SEC011 HTML</body></html>';

        return [
            'HTML named PDF honest metadata' => [$html, 'pdf', 'text/html'],
            'HTML named PDF forged metadata' => [$html, 'pdf', 'application/pdf'],
            'SVG active document' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="window.__sec011Executed=1"></svg>', 'svg', 'image/svg+xml'],
            'SVG named PNG forged metadata' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="window.__sec011Executed=1"></svg>', 'png', 'image/png'],
            'JavaScript' => ['window.__sec011Executed=1;', 'js', 'application/javascript'],
            'XML' => ['<?xml version="1.0"?><root>SEC011 XML</root>', 'xml', 'application/xml'],
            'plain text' => ['SEC011 plain text', 'txt', 'text/plain'],
            'empty fake PDF' => ['', 'pdf', 'application/pdf'],
        ];
    }

    #[DataProvider('activeFiles')]
    public function test_old_active_or_unknown_bytes_are_attachment_even_with_misleading_metadata(string $bytes, string $extension, string $mime): void
    {
        $user = User::factory()->create();
        $upload = $this->createUploadFor($user, Client::factory()->create(), $bytes, $extension, $mime);
        $before = $upload->getAttributes();

        foreach (['preview', 'download'] as $action) {
            $response = $this->actingAs($user)->get(route('uploads.'.$action, ['upload' => $upload]));
            $this->assertFileResponse($response, 'application/octet-stream', 'attachment', $bytes);
        }

        $this->assertSame($before, $upload->fresh()->getAttributes());
        $this->assertSame($bytes, Storage::disk('local')->get($upload->path));
    }

    /** @return array<string, array{string, list<string>}> */
    public static function legitimateFiles(): array
    {
        return ['PDF' => ['pdf', ['application/pdf']], 'PNG raster' => ['png', ['image/png']], 'WAV audio' => ['wav', ['audio/x-wav', 'audio/wav', 'audio/vnd.wave']]];
    }

    /** @param list<string> $allowedMimes */
    #[DataProvider('legitimateFiles')]
    public function test_real_safe_bytes_remain_inline_and_downloadable_despite_wrong_db_mime(string $extension, array $allowedMimes): void
    {
        $bytes = $this->fixtureBytes($extension);
        $user = User::factory()->create();
        $upload = $this->createUploadFor($user, Client::factory()->create(), $bytes, $extension, 'text/html');
        $before = $upload->getAttributes();

        $preview = $this->actingAs($user)->get(route('uploads.preview', ['upload' => $upload]));
        $expectedMime = $preview->headers->get('Content-Type');
        $this->assertContains($expectedMime, $allowedMimes);
        $this->assertFileResponse($preview, $expectedMime, 'inline', $bytes);
        $download = $this->get(route('uploads.download', ['upload' => $upload]));
        $this->assertFileResponse($download, $expectedMime, 'attachment', $bytes);
        $this->assertSame($before, $upload->fresh()->getAttributes());
    }

    /** @return array<string, array{string, ?string}> */
    public static function incoherentExtensions(): array
    {
        return [
            'extension metadata' => ['extension', 'html'],
            'missing extension metadata' => ['extension', null],
            'original name' => ['original_name', 'manual.html'],
            'original name without extension' => ['original_name', 'manual'],
            'stored filename' => ['file_name', 'manual.html'],
            'stored path' => ['path', 'uploads/manual.html'],
        ];
    }

    #[DataProvider('incoherentExtensions')]
    public function test_safe_mime_requires_coherent_extension_and_all_file_names(string $field, ?string $value): void
    {
        $user = User::factory()->create();
        $upload = $this->createUploadFor($user, Client::factory()->create());
        $bytes = $this->fixtureBytes('pdf');
        if ($field === 'path') {
            Storage::disk('local')->put($value, $bytes);
        }
        $upload->forceFill([$field => $value])->save();
        $before = $upload->getAttributes();

        foreach (['preview', 'download'] as $action) {
            $response = $this->actingAs($user)->get(route('uploads.'.$action, ['upload' => $upload]));
            $this->assertFileResponse($response, 'application/octet-stream', 'attachment', $bytes);
        }
        $this->assertSame($before, $upload->fresh()->getAttributes());
    }

    public function test_unicode_quoted_original_filename_uses_vendor_disposition_encoding(): void
    {
        $user = User::factory()->create();
        $upload = $this->createUploadFor($user, Client::factory()->create());
        $name = 'Ação "contrato".PDF';
        $upload->forceFill(['original_name' => $name, 'extension' => 'PDF'])->save();

        foreach (['preview' => 'inline', 'download' => 'attachment'] as $action => $disposition) {
            $response = $this->actingAs($user)->get(route('uploads.'.$action, ['upload' => $upload]));
            $this->assertFileResponse($response, 'application/pdf', $disposition, $this->fixtureBytes('pdf'));
            $this->assertStringContainsString("filename*=utf-8''".rawurlencode($name), $response->headers->get('Content-Disposition'));
        }
    }

    public function test_missing_bytes_return_404_without_a_mime_probe(): void
    {
        $user = User::factory()->create();
        $upload = $this->createUploadFor($user, Client::factory()->create());
        $fake = Storage::disk('local');
        $fake->delete($upload->path);
        $observer = (object) ['attempts' => 0, 'reads' => []];
        $this->observeDisk($fake, $upload->path, $observer);

        foreach (['preview', 'download'] as $action) {
            $this->actingAs($user)->get(route('uploads.'.$action, ['upload' => $upload]))->assertNotFound();
        }
        $this->assertSame(0, $observer->attempts);
    }

    public function test_access_is_denied_before_disk_resolution_or_streaming(): void
    {
        $owner = User::factory()->create();
        $upload = $this->createUploadFor($owner, Client::factory()->create());
        $upload->forceFill(['parentable_id' => 999999999])->save();
        $other = User::factory()->create();
        Storage::shouldReceive('disk')->never();

        $this->get(route('uploads.preview', ['upload' => $upload]))->assertForbidden();
        $this->get(route('uploads.download', ['upload' => $upload]))->assertRedirect(route('login'));
        foreach (['preview', 'download'] as $action) {
            $this->actingAs($other)->get(route('uploads.'.$action, ['upload' => $upload]))->assertForbidden();
        }
    }

    /** @return array<string, array{string}> */
    public static function fileActions(): array
    {
        return ['preview' => ['preview'], 'download' => ['download']];
    }

    #[DataProvider('fileActions')]
    public function test_mime_probe_reads_at_most_64_kib_and_closes_before_response_streaming(string $action): void
    {
        $user = User::factory()->create();
        $bytes = $this->fixtureBytes('pdf').str_repeat(' ', 262144);
        $upload = $this->createUploadFor($user, Client::factory()->create(), $bytes);
        $observer = (object) ['attempts' => 0, 'reads' => []];
        $this->observeDisk(Storage::disk('local'), $upload->path, $observer);

        $response = $this->actingAs($user)->get(route('uploads.'.$action, ['upload' => $upload]));
        $this->assertSame(1, $observer->attempts);
        $probe = $observer->reads[0];
        $this->assertGreaterThan(0, $probe->bytes);
        $this->assertLessThanOrEqual(65536, $probe->bytes);
        $this->assertTrue($probe->closed);
        $this->assertFalse(is_resource($probe->stream));
        $this->assertFileResponse($response, 'application/pdf', $action === 'preview' ? 'inline' : 'attachment', $bytes);
    }

    /** @return array<string, array{string}> */
    public static function probeFailures(): array
    {
        return ['stream false' => ['false'], 'stream throws' => ['throw'], 'partial inconclusive bytes' => ['partial']];
    }

    #[DataProvider('probeFailures')]
    public function test_probe_failure_is_attachment_and_next_request_recovers(string $failure): void
    {
        $user = User::factory()->create();
        $upload = $this->createUploadFor($user, Client::factory()->create());
        $observer = (object) ['attempts' => 0, 'reads' => []];
        $this->observeDisk(Storage::disk('local'), $upload->path, $observer, $failure);

        $first = $this->actingAs($user)->get(route('uploads.preview', ['upload' => $upload]));
        $this->assertFileResponse($first, 'application/octet-stream', 'attachment', $this->fixtureBytes('pdf'));
        foreach ($observer->reads as $read) {
            $this->assertTrue($read->closed);
            $this->assertFalse(is_resource($read->stream));
        }
        $retry = $this->get(route('uploads.preview', ['upload' => $upload]));
        $this->assertFileResponse($retry, 'application/pdf', 'inline', $this->fixtureBytes('pdf'));
    }

    public function test_arbitrary_private_html_attachment_remains_uploadable_without_becoming_inline(): void
    {
        $user = User::factory()->create();
        $this->grantClientPermission($user, 'update-clients');
        $this->grantClientPermission($user, 'view-clients');
        $client = Client::factory()->create();
        Storage::fake('local');
        $bytes = '<!DOCTYPE html><script>window.__sec011Executed=1</script>';
        $incoming = $this->testEnvironment->path.'/sec011-incoming.pdf';
        file_put_contents($incoming, $bytes);
        $file = new UploadedFile($incoming, 'document.pdf', 'application/pdf', null, true);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/uploads', [
            'parentable_type' => 'client', 'parentable_id' => $client->id, 'files' => [$file],
        ])->assertCreated();
        $upload = Uploaded::query()->latest('id')->firstOrFail();
        $this->assertSame('text/html', $upload->mime_type);
        $response = $this->actingAs($user)->get(route('uploads.preview', ['upload' => $upload]));
        $this->assertFileResponse($response, 'application/octet-stream', 'attachment', $bytes);
    }

    private function grantClientPermission(User $user, string $slug): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'group' => 'clients'],
        );
        $user->permissions()->syncWithoutDetaching([$permission->getKey()]);
    }

    private function assertFileResponse(TestResponse $response, string $mime, string $disposition, string $bytes): void
    {
        $response->assertOk()
            ->assertHeader('Content-Type', $mime)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "script-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");
        $this->assertMatchesRegularExpression('/\A'.$disposition.'(?:;|\z)/', $response->headers->get('Content-Disposition'));
        $response->assertStreamedContent($bytes);
    }

    private function fixtureBytes(string $extension): string
    {
        $bytes = file_get_contents(__DIR__.'/../Fixtures/upload-preview.'.$extension);
        $this->assertIsString($bytes);

        return $bytes;
    }

    private function observeDisk(FilesystemAdapter $fake, string $ownedPath, stdClass $observer, ?string $failure = null): void
    {
        $filter = 'start-kit.sec011-read-counter';
        if (! in_array($filter, stream_get_filters(), true)) {
            $this->assertTrue(stream_filter_register($filter, UploadPreviewReadCounter::class));
        }
        $disk = new class($fake, $ownedPath, $observer, $failure, $filter) extends FilesystemAdapter
        {
            public function __construct(FilesystemAdapter $fake, private readonly string $ownedPath, private readonly stdClass $observer, private ?string $failure, private readonly string $filter)
            {
                parent::__construct($fake->getDriver(), $fake->getAdapter(), $fake->getConfig());
            }

            public function readStream(mixed $path): mixed
            {
                if ($path !== $this->ownedPath) {
                    throw new RuntimeException('Unexpected upload fixture path.');
                }
                $this->observer->attempts++;
                $failure = $this->failure;
                $this->failure = null;
                if ($failure === 'false') {
                    return false;
                }
                if ($failure === 'throw') {
                    throw new RuntimeException('Synthetic stream failure.');
                }
                $stream = $failure === 'partial' ? fopen('php://temp', 'w+b') : parent::readStream($path);
                if (! is_resource($stream)) {
                    throw new RuntimeException('Cannot open owned fixture stream.');
                }
                if ($failure === 'partial') {
                    fwrite($stream, '%PD');
                    rewind($stream);
                }
                $read = (object) ['bytes' => 0, 'closed' => false, 'stream' => $stream];
                if (stream_filter_append($stream, $this->filter, STREAM_FILTER_READ, $read) === false) {
                    fclose($stream);
                    throw new RuntimeException('Cannot attach fixture read counter.');
                }
                $this->observer->reads[] = $read;

                return $stream;
            }
        };
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    }

    /** Create an owner fixture with permission to view its parent. */
    private function createUploadFor(User $user, Client $client, ?string $bytes = null, string $extension = 'pdf', string $mime = 'application/pdf'): Uploaded
    {
        $this->grantClientPermission($user, 'view-clients');
        $bytes ??= $this->fixtureBytes('pdf');
        Storage::fake('local');
        $fileName = 'manual.'.$extension;
        $path = 'uploads/client/'.$client->uuid.'/'.$fileName;
        Storage::disk('local')->put($path, $bytes);

        return Uploaded::query()->create([
            'user_id' => $user->id,
            'uploader_id' => $user->id,
            'parentable_type' => Client::class,
            'parentable_id' => $client->getKey(),
            'name' => 'Manual',
            'file_name' => $fileName,
            'original_name' => $fileName,
            'mime_type' => $mime,
            'path' => $path,
            'extension' => $extension,
            'disk' => 'local',
            'size' => strlen($bytes),
        ])->refresh();
    }
}

class UploadPreviewReadCounter extends \php_user_filter
{
    public function filter(mixed $in, mixed $out, mixed &$consumed, bool $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $this->params->bytes += $bucket->datalen;
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }

    public function onClose(): void
    {
        $this->params->closed = true;
    }
}
