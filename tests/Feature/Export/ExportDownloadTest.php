<?php

namespace Tests\Feature\Export;

use App\Models\Export;
use App\Models\User;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportDownloadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    public function test_download_streams_file_for_completed_non_expired_export(): void
    {
        Storage::disk('local')->put('exports/test.csv', 'name,email');

        $export = Export::factory()->completed()->create([
            'user_id' => $this->user->id,
            'file_path' => 'exports/test.csv',
            'file_name' => 'export.csv',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/exports/{$export->uuid}/download");

        $response->assertOk();
        $response->assertDownload('export.csv');
        $this->assertSame('name,email', $response->streamedContent());
    }

    public function test_download_returns_403_for_non_owner(): void
    {
        Storage::disk('local')->put('exports/test.csv', 'name,email');

        $otherUser = User::factory()->create();
        $export = Export::factory()->completed()->create([
            'user_id' => $otherUser->id,
            'file_path' => 'exports/test.csv',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/exports/{$export->uuid}/download");

        $response->assertStatus(403);
    }

    public function test_download_returns_404_for_pending_export(): void
    {
        $export = Export::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get("/exports/{$export->uuid}/download");

        $response->assertStatus(404);
    }

    public function test_download_returns_410_for_expired_export(): void
    {
        $export = Export::factory()->expired()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get("/exports/{$export->uuid}/download");

        $response->assertStatus(410);
    }

    public function test_download_requires_authentication(): void
    {
        $export = Export::factory()->completed()->create(['user_id' => $this->user->id]);

        $response = $this->get("/exports/{$export->uuid}/download");

        $response->assertRedirect('/login');
    }

    public function test_administrator_cannot_download_another_users_export(): void
    {
        $this->user->roles()->attach(Role::factory()->create(['slug' => 'admin']));
        $export = Export::factory()->completed()->create();

        $this->actingAs($this->user)->get(route('exports.download', $export))->assertForbidden();
    }

    public function test_download_returns_not_found_when_file_is_missing(): void
    {
        $export = Export::factory()->completed()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->get(route('exports.download', $export))->assertNotFound();
    }
}
