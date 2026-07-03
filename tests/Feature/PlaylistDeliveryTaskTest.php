<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Actions\DeliveryTasks\CreatePlaylistDeliveryTask;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskStatus;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskType;
use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Http\Resources\PlaylistDeliveryTaskResource;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Sales\Actions\Status\ConfirmSale;
use App\Modules\Sales\Actions\Status\FinishSaleCampaign;
use App\Modules\Sales\Enums\SaleItemStatus;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlaylistDeliveryTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_sale_with_playlist_item_creates_add_delivery_task(): void
    {
        $user = $this->userWithPermissions(['sales.confirm']);
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 1, 'occupied_slots_count' => 0]);
        $sale = Sale::factory()->create(['seller_id' => $user->id, 'order_number' => 'VEN-PLAY-001']);
        $sale->applySystemStatus(SaleStatus::Quoted);
        SaleItem::factory()->for($sale)->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
            'service_id' => null,
            'playlist_top_id' => $playlistTop->id,
            'description' => 'Entrada em playlist',
            'quantity' => 1,
            'discount_amount' => 0,
            'track_name' => 'Musica de Entrada',
            'track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
            'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
            'track_artists' => [['name' => 'Artista Inicial']],
            'track_album_name' => 'Album Inicial',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
        ]);

        $this->actingAs($user);

        app(ConfirmSale::class)->handle($sale, confirmed: true);

        $slot = PlaylistSlot::query()->firstOrFail();
        $task = PlaylistDeliveryTask::query()->firstOrFail();

        $this->assertSame($slot->id, $task->playlist_slot_id);
        $this->assertSame($sale->id, $task->sale_id);
        $this->assertSame($playlistTop->id, $task->playlist_top_id);
        $this->assertSame($playlistTop->playlist_id, $task->playlist_id);
        $this->assertSame(PlaylistDeliveryTaskType::AddTrack, $task->type);
        $this->assertSame(PlaylistDeliveryTaskStatus::Pending, $task->status);
        $this->assertSame('VEN-PLAY-001', $task->sale_order_number);
        $this->assertSame($playlistTop->playlist->name, $task->playlist_name);
        $this->assertSame($playlistTop->top->name, $task->top_name);
        $this->assertSame('Musica de Entrada', $task->to_track_name);
        $this->assertSame('https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb', $task->to_track_spotify_url);
        $this->assertSame([['name' => 'Artista Inicial']], $task->to_track_artists);
        $this->assertNull($task->from_track_spotify_url);
    }

    public function test_confirming_sale_without_playlist_items_does_not_create_delivery_task(): void
    {
        $user = $this->userWithPermissions(['sales.confirm']);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::Quoted);
        SaleItem::factory()->for($sale)->create([
            'type' => SaleItemType::Service,
            'playlist_top_id' => null,
        ]);

        $this->actingAs($user);

        app(ConfirmSale::class)->handle($sale, confirmed: true);

        $this->assertDatabaseCount('playlist_delivery_tasks', 0);
    }

    public function test_finishing_campaign_creates_remove_task_cancels_pending_adds_and_is_idempotent(): void
    {
        $user = $this->userWithPermissions(['sales.finish']);
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 1, 'occupied_slots_count' => 1]);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::CampaignStarted);
        $item = SaleItem::factory()->for($sale)->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
            'service_id' => null,
            'playlist_top_id' => $playlistTop->id,
            'status' => SaleItemStatus::Active,
        ]);
        $slot = PlaylistSlot::factory()->for($playlistTop)->for($item)->create([
            'status' => PlaylistSlotStatus::Active,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
            'track_name' => 'Musica para Remover',
            'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
        ]);
        $item->forceFill([
            'sellable_type' => $slot->getMorphClass(),
            'sellable_id' => $slot->id,
        ])->save();

        app(CreatePlaylistDeliveryTask::class)->handle($slot, PlaylistDeliveryTaskType::AddTrack);

        $this->actingAs($user);

        app(FinishSaleCampaign::class)->handle($sale, confirmed: true);

        $this->assertSame(1, PlaylistDeliveryTask::query()->where('type', PlaylistDeliveryTaskType::RemoveTrack)->count());
        $this->assertSame(PlaylistDeliveryTaskStatus::Canceled, PlaylistDeliveryTask::query()->where('type', PlaylistDeliveryTaskType::AddTrack)->firstOrFail()->status);

        $removeTask = PlaylistDeliveryTask::query()->where('type', PlaylistDeliveryTaskType::RemoveTrack)->firstOrFail();

        $this->assertSame(PlaylistDeliveryTaskStatus::Pending, $removeTask->status);
        $this->assertSame('Musica para Remover', $removeTask->from_track_name);
        $this->assertSame('https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb', $removeTask->from_track_spotify_url);
        $this->assertNull($removeTask->to_track_spotify_url);
    }

    public function test_finishing_campaign_creates_remove_task_for_playlist_slot_without_sellable_pointer(): void
    {
        $user = $this->userWithPermissions(['sales.finish']);
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 1, 'occupied_slots_count' => 0]);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::CampaignStarted);
        $item = SaleItem::factory()->for($sale)->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
            'service_id' => null,
            'playlist_top_id' => $playlistTop->id,
            'status' => SaleItemStatus::Reserved,
            'sellable_type' => null,
            'sellable_id' => null,
        ]);
        $slot = PlaylistSlot::factory()->for($playlistTop)->for($item)->create([
            'status' => PlaylistSlotStatus::PendingSchedule,
            'track_name' => 'Musica sem morph',
            'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
        ]);

        app(CreatePlaylistDeliveryTask::class)->handle($slot, PlaylistDeliveryTaskType::AddTrack);

        $this->actingAs($user);

        app(FinishSaleCampaign::class)->handle($sale, confirmed: true);

        $this->assertSame(PlaylistSlotStatus::Finished, $slot->refresh()->status);
        $this->assertSame(PlaylistDeliveryTaskStatus::Canceled, PlaylistDeliveryTask::query()->where('type', PlaylistDeliveryTaskType::AddTrack)->firstOrFail()->status);
        $this->assertDatabaseHas('playlist_delivery_tasks', [
            'playlist_slot_id' => $slot->id,
            'type' => PlaylistDeliveryTaskType::RemoveTrack,
            'status' => PlaylistDeliveryTaskStatus::Pending,
            'from_track_name' => 'Musica sem morph',
            'from_track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
        ]);
    }

    public function test_delivery_task_completion_requires_permission(): void
    {
        $task = $this->createPendingTask();
        $unauthorized = User::factory()->create();

        $this->actingAs($unauthorized)
            ->patch(route('playlist-delivery-tasks.complete', $task), [
                'completion_note' => 'Feita no Spotify.',
            ])
            ->assertForbidden();

        $authorized = $this->userWithPermissions(['playlist-delivery-tasks.complete']);

        $this->actingAs($authorized)
            ->patch(route('playlist-delivery-tasks.complete', $task), [
                'completion_note' => 'Feita no Spotify.',
            ])
            ->assertRedirect();

        $task->refresh();

        $this->assertSame(PlaylistDeliveryTaskStatus::Completed, $task->status);
        $this->assertSame($authorized->id, $task->completed_by_id);
        $this->assertNotNull($task->completed_at);
        $this->assertSame('Feita no Spotify.', $task->metadata['completion_note']);
    }

    public function test_updating_started_slot_track_creates_replace_task_with_old_and_new_links(): void
    {
        $user = $this->userWithPermissions(['playlist-delivery-tasks.change-track']);
        $slot = $this->createStartedPlaylistSlot([
            'track_name' => 'Musica Antiga',
            'track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
            'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
            'track_artists' => [['name' => 'Artista Antigo']],
        ]);

        app(CreatePlaylistDeliveryTask::class)->handle($slot, PlaylistDeliveryTaskType::AddTrack)->forceFill([
            'status' => PlaylistDeliveryTaskStatus::Completed,
            'completed_at' => now(),
            'completed_by_id' => $user->id,
        ])->save();

        $this->actingAs($user)
            ->patch(route('playlist-slots.track.update', $slot), [
                'track_name' => 'Musica Nova',
                'track_spotify_url' => 'https://open.spotify.com/track/5dkWmb4kwbbhuemSkRWN5d',
                'track_artists' => [['name' => 'Artista Novo']],
                'track_album_name' => 'Album Novo',
            ])
            ->assertRedirect();

        $slot->refresh();
        $slot->saleItem->refresh();
        $replaceTask = PlaylistDeliveryTask::query()
            ->where('type', PlaylistDeliveryTaskType::ReplaceTrack)
            ->firstOrFail();

        $this->assertSame('Musica Nova', $slot->track_name);
        $this->assertSame('https://open.spotify.com/track/5dkWmb4kwbbhuemSkRWN5d', $slot->track_spotify_url);
        $this->assertSame('Musica Nova', $slot->saleItem->track_name);
        $this->assertSame('https://open.spotify.com/track/5dkWmb4kwbbhuemSkRWN5d', $slot->saleItem->track_spotify_url);
        $this->assertSame(PlaylistDeliveryTaskStatus::Pending, $replaceTask->status);
        $this->assertSame('Musica Antiga', $replaceTask->from_track_name);
        $this->assertSame('https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb', $replaceTask->from_track_spotify_url);
        $this->assertSame('Musica Nova', $replaceTask->to_track_name);
        $this->assertSame('https://open.spotify.com/track/5dkWmb4kwbbhuemSkRWN5d', $replaceTask->to_track_spotify_url);
    }

    public function test_api_index_requires_view_permission_and_returns_task_payload(): void
    {
        $task = $this->createPendingTask();

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.playlist-delivery-tasks.index'))
            ->assertForbidden();

        $user = $this->userWithPermissions(['playlist-delivery-tasks.view']);

        $this->actingAs($user)
            ->getJson(route('api.playlist-delivery-tasks.index', [
                'filter' => [
                    'status' => [
                        'operator' => 'equals',
                        'value' => PlaylistDeliveryTaskStatus::Pending->value,
                    ],
                    'type' => [
                        'operator' => 'equals',
                        'value' => PlaylistDeliveryTaskType::AddTrack->value,
                    ],
                    'playlist_id' => [
                        'operator' => 'equals',
                        'value' => $task->playlist_id,
                    ],
                    'sale_id' => [
                        'operator' => 'equals',
                        'value' => $task->sale_id,
                    ],
                ],
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $task->uuid)
            ->assertJsonPath('data.0.type_presenter.label', 'Adicionar')
            ->assertJsonPath('data.0.status_presenter.label', 'Pendente')
            ->assertJsonPath('data.0.to_track.name', $task->to_track_name);
    }

    public function test_index_page_renders_delivery_task_filters_and_permissions(): void
    {
        $user = $this->userWithPermissions([
            'playlist-delivery-tasks.view',
            'playlist-delivery-tasks.complete',
            'playlist-delivery-tasks.change-track',
        ]);

        $this->actingAs($user)
            ->get(route('playlists.delivery-tasks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/playlists/delivery-tasks/index')
                ->where('permissions.can_complete', true)
                ->where('permissions.can_change_track', true)
                ->has('filters.statuses', 3)
                ->has('filters.types', 3)
                ->where('filters.statuses.0.value', 'pending')
                ->where('filters.types.0.value', 'add_track')
            );
    }

    public function test_delivery_task_resource_exposes_presenters_and_preserves_track_snapshot(): void
    {
        $task = $this->createPendingTask();
        $slot = $task->playlistSlot;

        $slot->update([
            'track_name' => 'Musica alterada depois',
            'track_spotify_url' => 'https://open.spotify.com/track/aaaaaaaaaaaaaaaaaaaaaa',
        ]);

        $payload = (new PlaylistDeliveryTaskResource($task->refresh()))->resolve();

        $this->assertSame('add_track', $payload['type']);
        $this->assertSame('Adicionar', $payload['type_presenter']['label']);
        $this->assertSame('pending', $payload['status']);
        $this->assertSame('Pendente', $payload['status_presenter']['label']);
        $this->assertSame($task->to_track_name, $payload['to_track_name']);
        $this->assertSame($task->to_track_spotify_url, $payload['to_track_spotify_url']);
        $this->assertSame($task->to_track_name, $payload['track']['name']);
        $this->assertSame($task->to_track_spotify_url, $payload['track']['spotify_url']);
        $this->assertSame($task->to_track_name, $payload['to_track']['name']);
        $this->assertSame($task->to_track_spotify_url, $payload['to_track']['spotify_url']);
        $this->assertNotSame($slot->track_spotify_url, $payload['to_track']['spotify_url']);
    }

    /**
     * @param  array<string, mixed>  $slotOverrides
     */
    private function createStartedPlaylistSlot(array $slotOverrides = []): PlaylistSlot
    {
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 1, 'occupied_slots_count' => 1]);
        $sale = Sale::factory()->create();
        $sale->applySystemStatus(SaleStatus::CampaignStarted);
        $item = SaleItem::factory()->for($sale)->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
            'service_id' => null,
            'playlist_top_id' => $playlistTop->id,
            'status' => SaleItemStatus::Active,
            'track_name' => $slotOverrides['track_name'] ?? 'Musica Operacional',
            'track_spotify_id' => $slotOverrides['track_spotify_id'] ?? '4kwbbhuemSkRWvtN5dkWmb',
            'track_spotify_url' => $slotOverrides['track_spotify_url'] ?? 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
            'track_artists' => $slotOverrides['track_artists'] ?? [['name' => 'Artista Operacional']],
            'track_album_name' => $slotOverrides['track_album_name'] ?? 'Album Operacional',
        ]);
        $slot = PlaylistSlot::factory()->for($playlistTop)->for($item)->create([
            'status' => PlaylistSlotStatus::Active,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
            'track_name' => $item->track_name,
            'track_spotify_id' => $item->track_spotify_id,
            'track_spotify_url' => $item->track_spotify_url,
            'track_artists' => $item->track_artists,
            'track_album_name' => $item->track_album_name,
        ]);

        $item->forceFill([
            'sellable_type' => $slot->getMorphClass(),
            'sellable_id' => $slot->id,
        ])->save();

        return $slot->load(['saleItem.sale.client', 'playlistTop.playlist', 'playlistTop.top']);
    }

    private function createPendingTask(): PlaylistDeliveryTask
    {
        $slot = $this->createStartedPlaylistSlot([
            'track_name' => 'Musica na Fila',
            'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
        ]);

        return app(CreatePlaylistDeliveryTask::class)->handle($slot, PlaylistDeliveryTaskType::AddTrack);
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     */
    private function userWithPermissions(array $permissionSlugs): User
    {
        $user = User::factory()->create();

        $permissionIds = collect($permissionSlugs)
            ->map(fn (string $slug): int => Permission::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace('.', ' ')->title()->toString(),
                    'group' => str($slug)->before('.')->toString(),
                ],
            )->id)
            ->all();

        $user->permissions()->sync($permissionIds);
        $user->load(['roles', 'permissions']);

        return $user;
    }
}
