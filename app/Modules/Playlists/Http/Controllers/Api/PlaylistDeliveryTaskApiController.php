<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Modules\Playlists\Http\Resources\PlaylistDeliveryTaskResource;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use App\Supports\Api\ApiDisplay;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PlaylistDeliveryTaskApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PlaylistDeliveryTask::class);

        return ApiDisplay::for(PlaylistDeliveryTask::class, PlaylistDeliveryTaskResource::class)
            ->usingQuery(
                PlaylistDeliveryTask::query()
                    ->with(['sale', 'playlist', 'playlistTop.playlist', 'playlistTop.top'])
            )
            ->paginate($request);
    }
}
