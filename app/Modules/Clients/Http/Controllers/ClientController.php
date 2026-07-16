<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clients\Http\Requests\Client\QuickUpdateClientRequest;
use App\Modules\Clients\Http\Requests\Client\StoreClientRequest;
use App\Modules\Clients\Http\Requests\Client\UpdateClientRequest;
use App\Modules\Clients\Http\Resources\ClientResource;
use App\Modules\Clients\Models\Client;
use App\Modules\Playlists\Http\Resources\MusicGenreResource;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\Playlist;
use App\Supports\ActivityActions\ActivityActionLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('app/clients/index', [
            'quickFilterOptions' => $this->quickFilterOptions(),
        ]);
    }

    public function split(): Response
    {
        return Inertia::render('app/clients/split/index');
    }

    public function create(): Response
    {
        return Inertia::render('app/clients/create', [
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = Client::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client created.')]);

        return to_route('clients.show', $client);
    }

    public function show(Request $request, Client $client, ActivityActionLogger $activityLogger): Response
    {
        // $activityLogger->log(
        //    actionKey: 'clients.viewed',
        //    subject: $client,
        //    causer: $request->user(),
        //    request: $request,
        // );

        return Inertia::render('app/clients/show', [
            'clientData' => new ClientResource($client->load(['responsibleUser', 'addresses'])),
            'timelineSubject' => [
                'type' => $client::timelineType(),
                'id' => $client->uuid,
            ],
        ]);
    }

    public function edit(Client $client): Response
    {
        Gate::authorize('update', $client);

        return Inertia::render('app/clients/edit', [
            'clientData' => new ClientResource($client->load(['responsibleUser', 'addresses'])),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $client->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client updated.')]);

        return to_route('clients.show', $client);
    }

    public function quickUpdate(QuickUpdateClientRequest $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $client->update($request->updatePayload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client updated.')]);

        return back();
    }

    public function updateStatus(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
        ]);

        $client->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client status updated.')]);

        return back();
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client deleted.')]);

        return to_route('clients.index', [], 303);
    }

    /**
     * @return array{
     *     classifications: array<int, array{label: string, value: string}>,
     *     musicalGenres: array<int, array{label: string, value: string}>
     * }
     */
    private function quickFilterOptions(): array
    {
        return [
            'classifications' => collect(Client::CLASSIFICATIONS)
                ->map(fn (string $classification): array => [
                    'label' => $classification,
                    'value' => $classification,
                ])
                ->values()
                ->all(),
            'musicalGenres' => Client::query()
                ->whereNotNull('musical_genre')
                ->where('musical_genre', '<>', '')
                ->distinct()
                ->orderBy('musical_genre')
                ->pluck('musical_genre')
                ->map(fn (string $genre): array => [
                    'label' => $genre,
                    'value' => $genre,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     genres: array<int, array<string, mixed>>,
     *     can_create_music_catalog: bool
     * }
     */
    private function formOptions(): array
    {
        return [
            'genres' => MusicGenreResource::collection(
                MusicGenre::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
            )->resolve(),
            'can_create_music_catalog' => auth()->user()?->can('create', Playlist::class) ?? false,
        ];
    }
}
