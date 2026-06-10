<?php

namespace Tests\Feature\Export;

use App\Models\Export;
use App\Models\User;
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
}
