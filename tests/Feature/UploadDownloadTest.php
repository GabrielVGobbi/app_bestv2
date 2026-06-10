<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Uploads\Jobs\GenerateZipJob;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Models\ZipRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_list_client_uploads(): void
    {
        $user = User::factory()->create();
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

    public function test_authorized_user_can_upload_small_file_for_client(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $client = Client::factory()->create([
            'uuid' => '6466cc5c-f28c-4491-9c6c-80c27efe6114',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/uploads', [
                'parentable_type' => 'client',
                'parentable_id' => $client->id,
                'tag' => 'document',
                'files' => [UploadedFile::fake()->create('contract.pdf', 12, 'application/pdf')],
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

    public function test_upload_rejects_unknown_alias(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/uploads', [
                'parentable_type' => 'product',
                'parentable_id' => 1,
                'files' => [UploadedFile::fake()->create('contract.pdf', 12, 'application/pdf')],
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

    private function createUploadFor(User $user, Client $client): Uploaded
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/client/'.$client->uuid.'/manual.pdf', 'manual');

        return Uploaded::query()->create([
            'user_id' => $user->id,
            'uploader_id' => $user->id,
            'parentable_type' => Client::class,
            'parentable_id' => $client->getKey(),
            'name' => 'Manual',
            'file_name' => 'manual.pdf',
            'original_name' => 'manual.pdf',
            'mime_type' => 'application/pdf',
            'path' => 'uploads/client/'.$client->uuid.'/manual.pdf',
            'extension' => 'pdf',
            'disk' => 'local',
            'size' => 6,
        ]);
    }
}
