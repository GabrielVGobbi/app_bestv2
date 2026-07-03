<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Actions\DeliveryTasks;

use App\Models\User;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskStatus;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskType;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Support\SpotifyTrackInputParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePlaylistSlotTrack
{
    public function __construct(
        private readonly SpotifyTrackInputParser $spotifyTrackInputParser,
        private readonly CreatePlaylistDeliveryTask $createPlaylistDeliveryTask,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PlaylistSlot $playlistSlot, array $data, User $user): PlaylistSlot
    {
        return DB::transaction(function () use ($playlistSlot, $data): PlaylistSlot {
            $slot = PlaylistSlot::query()
                ->with(['saleItem.sale', 'playlistTop.playlist', 'playlistTop.top'])
                ->whereKey($playlistSlot->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $sale = $slot->saleItem?->sale;

            if ($sale?->status !== SaleStatus::CampaignStarted) {
                throw ValidationException::withMessages([
                    'status' => __('A música só pode ser trocada em campanha iniciada.'),
                ]);
            }

            $trackReference = $data['track_spotify_url'] ?? $data['track_spotify_id'] ?? null;
            $trackSpotifyId = $this->spotifyTrackInputParser->parse($trackReference);
            $trackSpotifyUrl = $this->spotifyTrackInputParser->canonicalUrl($trackReference);

            if ($trackSpotifyId === null || $trackSpotifyUrl === null) {
                throw ValidationException::withMessages([
                    'track_spotify_url' => __('Informe um link, URI ou ID válido de música do Spotify.'),
                ]);
            }

            $fromTrack = $this->createPlaylistDeliveryTask->trackSnapshot($slot);
            $metadata = $this->trackMetadata($data);
            $payload = [
                'track_spotify_id' => $trackSpotifyId,
                'track_spotify_url' => $trackSpotifyUrl,
                'track_name' => $data['track_name'],
                'track_artists' => $data['track_artists'] ?? null,
                'track_album_name' => $data['track_album_name'] ?? null,
                'track_duration_ms' => $data['track_duration_ms'] ?? null,
                'track_isrc' => $data['track_isrc'] ?? null,
                'track_metadata' => $metadata,
            ];

            $slot->forceFill($payload)->save();
            $slot->saleItem?->forceFill($payload)->save();

            $toTrack = $this->createPlaylistDeliveryTask->trackSnapshot($slot->refresh());

            $completedAddExists = PlaylistDeliveryTask::query()
                ->where('playlist_slot_id', $slot->id)
                ->where('type', PlaylistDeliveryTaskType::AddTrack)
                ->where('status', PlaylistDeliveryTaskStatus::Completed)
                ->exists();

            if ($completedAddExists) {
                $this->createPlaylistDeliveryTask->handle(
                    playlistSlot: $slot,
                    type: PlaylistDeliveryTaskType::ReplaceTrack,
                    fromTrack: $fromTrack,
                    toTrack: $toTrack,
                );
            } else {
                PlaylistDeliveryTask::query()
                    ->where('playlist_slot_id', $slot->id)
                    ->where('type', PlaylistDeliveryTaskType::AddTrack)
                    ->where('status', PlaylistDeliveryTaskStatus::Pending)
                    ->update([
                        'to_track_spotify_id' => $toTrack['spotify_id'],
                        'to_track_spotify_url' => $toTrack['spotify_url'],
                        'to_track_name' => $toTrack['name'],
                        'to_track_artists' => $toTrack['artists'],
                        'to_track_album_name' => $toTrack['album_name'],
                        'to_track_duration_ms' => $toTrack['duration_ms'],
                        'to_track_isrc' => $toTrack['isrc'],
                        'to_track_image_url' => $toTrack['image_url'],
                    ]);
            }

            return $slot->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function trackMetadata(array $data): ?array
    {
        $metadata = is_array($data['track_metadata'] ?? null) ? $data['track_metadata'] : [];

        if (filled($data['track_image_url'] ?? null)) {
            $metadata['image_url'] = $data['track_image_url'];
        }

        return $metadata === [] ? null : $metadata;
    }
}
