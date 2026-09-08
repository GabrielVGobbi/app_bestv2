<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Sales\Actions\CreateSaleDraft;
use App\Modules\Sales\Actions\Items\SyncSaleItemsFromPayload;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Http\Requests\StoreSaleRequest;
use App\Modules\Sales\Http\Requests\UpdateSaleRequest;
use App\Modules\Sales\Http\Resources\SaleResource;
use App\Modules\Sales\Models\Sale;
use App\Modules\Services\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Sale::class);
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return Inertia::render('app/sales/index', [
            'permissions' => $this->permissions($user),
            'filters' => $this->indexFilters($user),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Sale::class);

        return Inertia::render('app/sales/create', [
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function store(StoreSaleRequest $request, CreateSaleDraft $createSaleDraft): RedirectResponse
    {
        $validated = $this->mergeTrackMetadata($request->validated(), $request->input('items', []));
        $sale = $createSaleDraft->handle($validated, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Venda criada.')]);

        return to_route('sales.show', $sale);
    }

    public function show(Sale $sale): Response
    {
        Gate::authorize('view', $sale);

        return Inertia::render('app/sales/show', [
            'saleData' => new SaleResource($sale->load([
                'client',
                'company',
                'seller',
                'saleItems.service',
                'saleItems.playlistTop.playlist',
                'saleItems.playlistTop.top',
                'saleItems.sellable',
            ])),
            'timelineSubject' => [
                'type' => Sale::timelineType(),
                'id' => $sale->uuid,
            ],
            'permissions' => $this->permissions(auth()->user(), $sale),
        ]);
    }

    public function edit(Sale $sale): Response
    {
        Gate::authorize('update', $sale);

        return Inertia::render('app/sales/edit', [
            'saleData' => new SaleResource($sale->load([
                'company',
                'client',
                'seller',
                'saleItems.service',
                'saleItems.playlistTop.playlist',
                'saleItems.playlistTop.top',
            ])),
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function update(UpdateSaleRequest $request, Sale $sale, SyncSaleItemsFromPayload $syncSaleItemsFromPayload): RedirectResponse
    {
        $validated = $this->mergeTrackMetadata($request->validated(), $request->input('items', []));
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        DB::transaction(function () use ($sale, $validated, $user, $syncSaleItemsFromPayload): void {
            $discountAmount = $validated['discount_amount'] ?? $sale->discount_amount;

            $sale->update([
                'company_id' => $validated['company_id'],
                'client_id' => $validated['client_id'],
                'title' => $validated['title'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'discount_amount' => $discountAmount,
                'total_amount' => max(0, $sale->subtotal_amount - $discountAmount),
            ]);

            if (array_key_exists('items', $validated)) {
                $syncSaleItemsFromPayload->handle($sale->refresh(), $validated['items'] ?? [], $user);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Venda atualizada.')]);

        return to_route('sales.show', $sale);
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        Gate::authorize('delete', $sale);

        $sale->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Venda arquivada.')]);

        return to_route('sales.index', [], 303);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mergeTrackMetadata(array $validated, mixed $rawItems): array
    {
        if (! is_array($rawItems) || ! is_array($validated['items'] ?? null)) {
            return $validated;
        }

        foreach ($validated['items'] as $index => $item) {
            $rawItem = $rawItems[$index] ?? null;

            if (! is_array($item) || ! is_array($rawItem) || blank($rawItem['track_image_url'] ?? null)) {
                continue;
            }

            $validated['items'][$index]['track_image_url'] = $rawItem['track_image_url'];
            $validated['items'][$index]['track_metadata'] = [
                ...(is_array($item['track_metadata'] ?? null) ? $item['track_metadata'] : []),
                'image_url' => $rawItem['track_image_url'],
            ];
        }

        return $validated;
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'companies' => Company::query()
                ->orderBy('name')
                ->get(['id', 'uuid', 'name', 'nickname', 'cnpj'])
                ->map(fn (Company $company): array => [
                    'id' => $company->id,
                    'uuid' => $company->uuid,
                    'name' => $company->name,
                    'nickname' => $company->nickname,
                    'cnpj' => $company->cnpj,
                ])
                ->all(),
            'clients' => Client::query()
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'uuid', 'name', 'email', 'phone'])
                ->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'uuid' => $client->uuid,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                ])
                ->all(),
            'services' => Service::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'uuid', 'name', 'base_price', 'currency'])
                ->map(fn (Service $service): array => [
                    'id' => $service->id,
                    'uuid' => $service->uuid,
                    'name' => $service->name,
                    'base_price' => $service->base_price,
                    'currency' => $service->currency,
                ])
                ->all(),
            'playlists' => Playlist::query()
                ->with('spotifyCoverUpload:id')
                ->whereHas('playlistTops', fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get(['id', 'uuid', 'name', 'spotify_image_url', 'spotify_cover_upload_id'])
                ->map(fn (Playlist $playlist): array => [
                    'id' => $playlist->id,
                    'uuid' => $playlist->uuid,
                    'name' => $playlist->name,
                    'spotify_image_url' => $playlist->spotify_image_url,
                    'cover_image_url' => $playlist->coverImageUrl(),
                ])
                ->all(),
            'playlist_tops' => PlaylistTop::query()
                ->with(['playlist:id,name,spotify_image_url,spotify_cover_upload_id', 'playlist.spotifyCoverUpload:id', 'top:id,name'])
                ->where('is_active', true)
                ->orderBy('playlist_id')
                ->orderByDesc('id')
                ->limit(100)
                ->get(['id', 'uuid', 'playlist_id', 'top_id', 'slots_quantity', 'occupied_slots_count', 'price', 'currency'])
                ->map(fn (PlaylistTop $playlistTop): array => [
                    'id' => $playlistTop->id,
                    'uuid' => $playlistTop->uuid,
                    'playlist_id' => $playlistTop->playlist_id,
                    'playlist_name' => $playlistTop->playlist?->name,
                    'top_name' => $playlistTop->top?->name,
                    'name' => trim(($playlistTop->playlist?->name ?? 'Playlist').' - '.($playlistTop->top?->name ?? 'TOP')),
                    'spotify_image_url' => $playlistTop->playlist?->spotify_image_url,
                    'cover_image_url' => $playlistTop->playlist?->coverImageUrl(),
                    'slots_quantity' => $playlistTop->slots_quantity,
                    'occupied_slots_count' => $playlistTop->occupied_slots_count,
                    'price' => $playlistTop->price,
                    'currency' => $playlistTop->currency,
                ])
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function indexFilters(User $user): array
    {
        $visibleSales = Sale::query()->visibleTo($user);

        return [
            'statuses' => SaleStatus::options(),
            'companies' => Company::query()
                ->whereIn('id', (clone $visibleSales)->select('company_id'))
                ->orderBy('name')
                ->get(['id', 'uuid', 'name', 'nickname'])
                ->map(fn (Company $company): array => [
                    'value' => $company->id,
                    'label' => $company->nickname ?: $company->name,
                ])
                ->values()
                ->all(),
            'clients' => Client::query()
                ->whereIn('id', (clone $visibleSales)->select('client_id'))
                ->orderBy('name')
                ->get(['id', 'uuid', 'name', 'email'])
                ->map(fn (Client $client): array => [
                    'value' => $client->id,
                    'label' => $client->name,
                ])
                ->values()
                ->all(),
            'sellers' => User::query()
                ->whereIn('id', (clone $visibleSales)->whereNotNull('seller_id')->select('seller_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $seller): array => [
                    'value' => $seller->id,
                    'label' => $seller->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, bool> */
    private function permissions(mixed $user, ?Sale $sale = null): array
    {
        if (! $user instanceof User) {
            return [
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
                'can_quote' => false,
                'can_confirm' => false,
                'can_finish' => false,
                'can_cancel' => false,
                'can_verify_spotify_playlist' => false,
            ];
        }

        $saleModel = $sale ?? new Sale;

        return [
            'can_create' => $user->can('create', Sale::class),
            'can_update' => $user->can('update', $saleModel),
            'can_delete' => $user->can('delete', $saleModel),
            'can_quote' => $user->can('quote', $saleModel),
            'can_confirm' => $user->can('confirm', $saleModel),
            'can_finish' => $user->can('finish', $saleModel),
            'can_cancel' => $user->can('cancel', $saleModel),
            'can_verify_spotify_playlist' => $user->can('view', $saleModel) && $this->hasPermission($user, 'sales.items.spotify-playlist.verify'),
        ];
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }
}
