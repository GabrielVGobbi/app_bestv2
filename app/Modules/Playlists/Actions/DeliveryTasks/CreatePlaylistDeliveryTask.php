<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Actions\DeliveryTasks;

use App\Modules\Playlists\Enums\PlaylistDeliveryTaskStatus;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskType;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use App\Modules\Playlists\Models\PlaylistSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePlaylistDeliveryTask
{
    /**
     * @param  array<string, mixed>|null  $fromTrack
     * @param  array<string, mixed>|null  $toTrack
     */
    public function handle(
        PlaylistSlot $playlistSlot,
        PlaylistDeliveryTaskType $type,
        ?array $fromTrack = null,
        ?array $toTrack = null,
    ): PlaylistDeliveryTask {
        return DB::transaction(function () use ($playlistSlot, $type, $fromTrack, $toTrack): PlaylistDeliveryTask {
            $slot = PlaylistSlot::query()
                ->with(['saleItem.sale.client', 'playlistTop.playlist', 'playlistTop.top'])
                ->whereKey($playlistSlot->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($type !== PlaylistDeliveryTaskType::ReplaceTrack) {
                $existing = PlaylistDeliveryTask::query()
                    ->where('playlist_slot_id', $slot->id)
                    ->where('type', $type)
                    ->where('status', '!=', PlaylistDeliveryTaskStatus::Canceled)
                    ->first();

                if ($existing instanceof PlaylistDeliveryTask) {
                    return $existing;
                }
            }

            if ($type === PlaylistDeliveryTaskType::ReplaceTrack) {
                if ($fromTrack === null || $toTrack === null) {
                    throw ValidationException::withMessages([
                        'track' => __('Informe a música anterior e a nova música para editar a entrega.'),
                    ]);
                }

                PlaylistDeliveryTask::query()
                    ->where('playlist_slot_id', $slot->id)
                    ->where('type', PlaylistDeliveryTaskType::ReplaceTrack)
                    ->where('status', PlaylistDeliveryTaskStatus::Pending)
                    ->update([
                        'status' => PlaylistDeliveryTaskStatus::Canceled,
                        'canceled_at' => now(),
                        'cancellation_reason' => __('Substituída por uma nova edição de música.'),
                    ]);
            }

            return PlaylistDeliveryTask::query()->create([
                ...$this->basePayload($slot),
                'type' => $type,
                'status' => PlaylistDeliveryTaskStatus::Pending,
                ...$this->fromTrackPayload($type === PlaylistDeliveryTaskType::AddTrack ? null : ($fromTrack ?? $this->trackSnapshot($slot))),
                ...$this->toTrackPayload($type === PlaylistDeliveryTaskType::RemoveTrack ? null : ($toTrack ?? $this->trackSnapshot($slot))),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function trackSnapshot(PlaylistSlot $playlistSlot): array
    {
        return [
            'spotify_id' => $playlistSlot->track_spotify_id,
            'spotify_url' => $playlistSlot->track_spotify_url,
            'name' => $playlistSlot->track_name,
            'artists' => $playlistSlot->track_artists,
            'album_name' => $playlistSlot->track_album_name,
            'duration_ms' => $playlistSlot->track_duration_ms,
            'isrc' => $playlistSlot->track_isrc,
            'image_url' => is_array($playlistSlot->track_metadata)
                ? ($playlistSlot->track_metadata['image_url'] ?? null)
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function basePayload(PlaylistSlot $slot): array
    {
        $saleItem = $slot->saleItem;
        $sale = $saleItem?->sale;
        $playlistTop = $slot->playlistTop;

        return [
            'playlist_slot_id' => $slot->id,
            'sale_item_id' => $saleItem?->id,
            'sale_id' => $sale?->id,
            'playlist_top_id' => $playlistTop?->id,
            'playlist_id' => $playlistTop?->playlist_id,
            'sale_order_number' => $sale?->order_number,
            'client_name' => $sale?->client?->name,
            'playlist_name' => $playlistTop?->playlist?->name,
            'top_name' => $playlistTop?->top?->name,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $track
     * @return array<string, mixed>
     */
    private function fromTrackPayload(?array $track): array
    {
        return $this->trackPayload('from', $track);
    }

    /**
     * @param  array<string, mixed>|null  $track
     * @return array<string, mixed>
     */
    private function toTrackPayload(?array $track): array
    {
        return $this->trackPayload('to', $track);
    }

    /**
     * @param  array<string, mixed>|null  $track
     * @return array<string, mixed>
     */
    private function trackPayload(string $prefix, ?array $track): array
    {
        return [
            "{$prefix}_track_spotify_id" => $track['spotify_id'] ?? null,
            "{$prefix}_track_spotify_url" => $track['spotify_url'] ?? null,
            "{$prefix}_track_name" => $track['name'] ?? null,
            "{$prefix}_track_artists" => $track['artists'] ?? null,
            "{$prefix}_track_album_name" => $track['album_name'] ?? null,
            "{$prefix}_track_duration_ms" => $track['duration_ms'] ?? null,
            "{$prefix}_track_isrc" => $track['isrc'] ?? null,
            "{$prefix}_track_image_url" => $track['image_url'] ?? null,
        ];
    }
}
