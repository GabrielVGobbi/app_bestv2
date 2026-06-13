<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Modules\Playlists\Http\Requests\Top\StoreTopRequest;
use App\Modules\Playlists\Http\Requests\Top\UpdateTopRequest;
use App\Modules\Playlists\Http\Resources\TopResource;
use App\Modules\Playlists\Models\Top;
use App\Supports\Api\ApiDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TopApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Top::class);

        return ApiDisplay::for(Top::class, TopResource::class)->paginate($request);
    }

    public function store(StoreTopRequest $request): TopResource
    {
        $top = Top::create($request->payload());

        return new TopResource($top);
    }

    public function show(Top $top): TopResource
    {
        Gate::authorize('view', $top);

        return new TopResource($top->loadCount('playlistTops'));
    }

    public function update(UpdateTopRequest $request, Top $top): TopResource
    {
        $top->update($request->payload());

        return new TopResource($top->loadCount('playlistTops'));
    }

    public function destroy(Top $top): JsonResponse
    {
        Gate::authorize('delete', $top);

        $top->delete();

        return response()->json(null, 204);
    }

    public function restore(string $top): TopResource
    {
        $topModel = Top::withTrashed()->whereUuid($top)->firstOrFail();

        Gate::authorize('restore', $topModel);

        $topModel->restore();

        return new TopResource($topModel);
    }
}
