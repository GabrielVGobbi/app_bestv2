<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Playlists\Database\Seeders\PlaylistSeeder;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskStatus;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskType;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use App\Modules\Sales\Models\Sale;
use Database\Seeders\PlaylistDeliveryTaskDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaylistDeliveryTaskDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_idempotent_playlist_delivery_queue_scenario(): void
    {
        $this->seed(PlaylistSeeder::class);
        $this->seed(PlaylistDeliveryTaskDemoSeeder::class);
        $this->seed(PlaylistDeliveryTaskDemoSeeder::class);

        $tasks = PlaylistDeliveryTask::query()
            ->where('sale_order_number', 'like', 'PLTASK-DEMO-%')
            ->get();

        $this->assertCount(6, $tasks);
        $this->assertSame(5, Sale::query()->where('order_number', 'like', 'PLTASK-DEMO-%')->count());
        $this->assertSame(
            '6aBcDeFgHiJkLmNoPqRsTu',
            Playlist::query()->where('slug', 'fila-playlists-seeder-demo')->value('spotify_playlist_id'),
        );
        $this->assertSame(1, User::query()->where('email', 'gestor.playlists.seed@bestplay.local')->count());
        $this->assertTrue(
            User::query()
                ->where('email', 'gestor.playlists.seed@bestplay.local')
                ->whereHas('roles', fn ($roles) => $roles->where('slug', 'operation'))
                ->exists()
        );

        $this->assertSame(3, $tasks->where('status', PlaylistDeliveryTaskStatus::Pending)->count());
        $this->assertSame(2, $tasks->where('status', PlaylistDeliveryTaskStatus::Completed)->count());
        $this->assertSame(1, $tasks->where('status', PlaylistDeliveryTaskStatus::Canceled)->count());
        $this->assertSame(4, $tasks->where('type', PlaylistDeliveryTaskType::AddTrack)->count());
        $this->assertSame(1, $tasks->where('type', PlaylistDeliveryTaskType::RemoveTrack)->count());
        $this->assertSame(1, $tasks->where('type', PlaylistDeliveryTaskType::ReplaceTrack)->count());

        $replaceTask = $tasks->firstWhere('type', PlaylistDeliveryTaskType::ReplaceTrack);

        $this->assertNotNull($replaceTask);
        $this->assertNotNull($replaceTask->from_track_spotify_url);
        $this->assertNotNull($replaceTask->to_track_spotify_url);
        $this->assertNotSame($replaceTask->from_track_spotify_url, $replaceTask->to_track_spotify_url);
    }
}
