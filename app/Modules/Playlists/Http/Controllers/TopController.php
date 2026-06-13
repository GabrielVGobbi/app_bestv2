<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Playlists\Http\Requests\Top\StoreTopRequest;
use App\Modules\Playlists\Http\Requests\Top\UpdateTopRequest;
use App\Modules\Playlists\Http\Resources\TopResource;
use App\Modules\Playlists\Models\Top;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TopController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Top::class);

        return Inertia::render('app/playlist-tops/index', [
            'permissions' => $this->permissions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Top::class);

        return Inertia::render('app/playlist-tops/create');
    }

    public function store(StoreTopRequest $request): RedirectResponse
    {
        $top = Top::create($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('TOP criado.')]);

        return to_route('playlist-tops.show', $top);
    }

    public function show(Top $top): Response
    {
        Gate::authorize('view', $top);

        return Inertia::render('app/playlist-tops/show', [
            'topData' => new TopResource($top->loadCount('playlistTops')),
            'permissions' => $this->permissions($top),
        ]);
    }

    public function edit(Top $top): Response
    {
        Gate::authorize('update', $top);

        return Inertia::render('app/playlist-tops/edit', [
            'topData' => new TopResource($top->loadCount('playlistTops')),
            'permissions' => $this->permissions($top),
        ]);
    }

    public function update(UpdateTopRequest $request, Top $top): RedirectResponse
    {
        $top->update($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('TOP atualizado.')]);

        return to_route('playlist-tops.show', $top);
    }

    public function destroy(Top $top): RedirectResponse
    {
        Gate::authorize('delete', $top);

        $top->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('TOP arquivado.')]);

        return to_route('playlist-tops.index', [], 303);
    }

    public function restore(string $top): RedirectResponse
    {
        $topModel = Top::withTrashed()->whereUuid($top)->firstOrFail();

        Gate::authorize('restore', $topModel);

        $topModel->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('TOP restaurado.')]);

        return to_route('playlist-tops.show', $topModel);
    }

    /** @return array<string, bool> */
    private function permissions(?Top $top = null): array
    {
        $topModel = $top ?? new Top;

        return [
            'can_create' => auth()->user()?->can('create', Top::class) ?? false,
            'can_update' => auth()->user()?->can('update', $topModel) ?? false,
            'can_delete' => auth()->user()?->can('delete', $topModel) ?? false,
            'can_restore' => auth()->user()?->can('restore', $topModel) ?? false,
        ];
    }
}
