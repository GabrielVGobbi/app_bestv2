<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Items;

use App\Models\User;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Sales\Enums\SaleItemStatus;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Support\SpotifyTrackInputParser;
use App\Modules\Services\Models\Service;
use App\Supports\ActivityActions\ActivityActionLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertSaleItem
{
    public function __construct(
        private readonly RecalculateSaleItemTotals $recalculateSaleItemTotals,
        private readonly SyncSaleTotals $syncSaleTotals,
        private readonly SpotifyTrackInputParser $spotifyTrackInputParser,
        private readonly ActivityActionLogger $activityLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Sale $sale, array $data, User $user, ?SaleItem $saleItem = null): SaleItem
    {
        if (! $sale->isEditable()) {
            throw ValidationException::withMessages([
                'status' => __('Itens só podem ser alterados em vendas editáveis.'),
            ]);
        }

        return DB::transaction(function () use ($sale, $data, $user, $saleItem): SaleItem {
            $type = SaleItemType::from((string) $data['type']);
            $payload = match ($type) {
                SaleItemType::Service => $this->servicePayload($data),
                SaleItemType::PlaylistSlot => $this->playlistSlotPayload($data),
            };

            $item = $saleItem ?? new SaleItem;
            $previousReference = $item->exists ? $this->itemReferenceSnapshot($item) : null;

            $item->fill([
                ...$payload,
                'sale_id' => $sale->id,
                'type' => $type,
                'quantity' => (int) ($data['quantity'] ?? 1),
                'discount_amount' => (int) ($data['discount_amount'] ?? 0),
                'currency' => 'BRL',
                'starts_on' => null,
                'ends_on' => null,
                'status' => $item->exists ? $item->status : SaleItemStatus::Draft,
                'metadata' => $data['metadata'] ?? null,
                'created_by_id' => $item->exists ? $item->created_by_id : $user->id,
            ]);

            $this->validateCommonPayload($item);

            $this->recalculateSaleItemTotals->handle($item);
            $item->save();

            $this->syncSaleTotals->handle($sale);
            $item = $item->refresh();

            if ($previousReference !== null) {
                $this->recordReferenceChanges($sale, $item, $user, $previousReference);
            }

            return $item;
        });
    }

    /** @param array<string, mixed> $data */
    private function servicePayload(array $data): array
    {
        $serviceId = $data['service_id'] ?? null;

        if ($serviceId === null) {
            throw ValidationException::withMessages(['service_id' => __('Informe o serviço vendido.')]);
        }

        $service = Service::query()->findOrFail($serviceId);

        return [
            'description' => $service->name,
            'unit_amount' => (int) ($data['unit_amount'] ?? $service->base_price),
            'service_id' => $service->id,
            'playlist_top_id' => null,
            'track_spotify_id' => null,
            'track_spotify_url' => null,
            'track_name' => null,
            'track_artists' => null,
            'track_album_name' => null,
            'track_duration_ms' => null,
            'track_isrc' => null,
            'track_metadata' => null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function playlistSlotPayload(array $data): array
    {
        $playlistTopId = $data['playlist_top_id'] ?? null;

        if ($playlistTopId === null) {
            throw ValidationException::withMessages(['playlist_top_id' => __('Informe o TOP da playlist.')]);
        }

        if (blank($data['track_name'] ?? null)) {
            throw ValidationException::withMessages(['track_name' => __('Informe a música da vaga de playlist.')]);
        }

        $trackReference = $data['track_spotify_url'] ?? $data['track_spotify_id'] ?? null;
        $trackSpotifyId = $this->spotifyTrackInputParser->parse($trackReference);
        $trackSpotifyUrl = $this->spotifyTrackInputParser->canonicalUrl($trackReference);

        if ($trackSpotifyId === null || $trackSpotifyUrl === null) {
            throw ValidationException::withMessages(['track_spotify_url' => __('Informe um link, URI ou ID válido de música do Spotify.')]);
        }

        $playlistTop = PlaylistTop::query()
            ->with(['playlist:id,name', 'top:id,name'])
            ->findOrFail($playlistTopId);

        return [
            'description' => trim(($playlistTop->playlist?->name ?? 'Playlist').' - '.($playlistTop->top?->name ?? 'TOP')),
            'unit_amount' => (int) ($data['unit_amount'] ?? $playlistTop->price),
            'service_id' => null,
            'playlist_top_id' => $playlistTop->id,
            'track_spotify_id' => $trackSpotifyId,
            'track_spotify_url' => $trackSpotifyUrl,
            'track_name' => $data['track_name'],
            'track_artists' => $data['track_artists'] ?? null,
            'track_album_name' => $data['track_album_name'] ?? null,
            'track_duration_ms' => $data['track_duration_ms'] ?? null,
            'track_isrc' => $data['track_isrc'] ?? null,
            'track_metadata' => $this->trackMetadata([
                ...$data,
                'image_url' => $data['track_image_url'] ?? null,
            ]),
        ];
    }

    /** @param array<string, mixed> $data */
    private function trackMetadata(array $data): ?array
    {
        $metadata = is_array($data['track_metadata'] ?? null) ? $data['track_metadata'] : [];

        if (filled($data['track_image_url'] ?? null)) {
            $metadata['image_url'] = $data['track_image_url'];
        }

        if (filled($data['image_url'] ?? null)) {
            $metadata['image_url'] = $data['image_url'];
        }

        return $metadata === [] ? null : $metadata;
    }

    private function validateCommonPayload(SaleItem $item): void
    {
        if ($item->quantity < 1) {
            throw ValidationException::withMessages(['quantity' => __('A quantidade deve ser maior que zero.')]);
        }

        if ($item->unit_amount < 0 || $item->discount_amount < 0) {
            throw ValidationException::withMessages(['unit_amount' => __('Os valores do item devem ser positivos.')]);
        }

        if ($item->starts_on !== null && $item->ends_on !== null && $item->ends_on->lt($item->starts_on)) {
            throw ValidationException::withMessages(['ends_on' => __('A data final deve ser igual ou posterior à data inicial.')]);
        }
    }

    /**
     * @return array{
     *     service_id: int|null,
     *     service_label: string|null,
     *     playlist_top_id: int|null,
     *     playlist_top_label: string|null
     * }
     */
    private function itemReferenceSnapshot(SaleItem $item): array
    {
        $item->load(['service:id,name', 'playlistTop.playlist:id,name', 'playlistTop.top:id,name']);

        return [
            'service_id' => $item->service_id,
            'service_label' => $item->service?->name,
            'playlist_top_id' => $item->playlist_top_id,
            'playlist_top_label' => $this->playlistTopLabel($item->playlistTop),
        ];
    }

    /**
     * @param  array{
     *      service_id: int|null,
     *      service_label: string|null,
     *      playlist_top_id: int|null,
     *      playlist_top_label: string|null
     *  }  $previousReference
     */
    private function recordReferenceChanges(Sale $sale, SaleItem $item, User $user, array $previousReference): void
    {
        $currentReference = $this->itemReferenceSnapshot($item);
        $attributes = [];
        $old = [];

        if ($previousReference['service_id'] !== $currentReference['service_id']) {
            $attributes['item_service'] = $currentReference['service_label'];
            $old['item_service'] = $previousReference['service_label'];
        }

        if ($previousReference['playlist_top_id'] !== $currentReference['playlist_top_id']) {
            $attributes['item_playlist_top'] = $currentReference['playlist_top_label'];
            $old['item_playlist_top'] = $previousReference['playlist_top_label'];
        }

        if ($attributes === []) {
            return;
        }

        $this->activityLogger->log(
            actionKey: 'sales.item.updated',
            subject: $sale,
            causer: $user,
            properties: [
                'attributes' => $attributes,
                'old' => $old,
                'item' => [
                    'uuid' => $item->uuid,
                    'type' => $item->type->value,
                    'description' => $item->description,
                ],
            ],
        );
    }

    private function playlistTopLabel(?PlaylistTop $playlistTop): ?string
    {
        if (! $playlistTop instanceof PlaylistTop) {
            return null;
        }

        return trim(($playlistTop->playlist?->name ?? 'Playlist').' - '.($playlistTop->top?->name ?? 'TOP'));
    }
}
