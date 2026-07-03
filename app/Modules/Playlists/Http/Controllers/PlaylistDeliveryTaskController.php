<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Playlists\Actions\DeliveryTasks\CompletePlaylistDeliveryTask;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskStatus;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskType;
use App\Modules\Playlists\Http\Requests\CompletePlaylistDeliveryTaskRequest;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlaylistDeliveryTaskController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', PlaylistDeliveryTask::class);

        return Inertia::render('app/playlists/delivery-tasks/index', [
            'filters' => [
                'statuses' => PlaylistDeliveryTaskStatus::options(),
                'types' => PlaylistDeliveryTaskType::options(),
            ],
            'permissions' => $this->permissions(),
        ]);
    }

    public function complete(
        CompletePlaylistDeliveryTaskRequest $request,
        PlaylistDeliveryTask $playlistDeliveryTask,
        CompletePlaylistDeliveryTask $completePlaylistDeliveryTask,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $completePlaylistDeliveryTask->handle(
            playlistDeliveryTask: $playlistDeliveryTask,
            user: $user,
            completionNote: $request->validated('completion_note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Checklist concluído.')]);

        return back();
    }

    /** @return array<string, bool> */
    private function permissions(): array
    {
        $task = new PlaylistDeliveryTask;
        $user = auth()->user();

        return [
            'can_complete' => $user?->can('complete', $task) ?? false,
            'can_change_track' => $user?->can('changeTrack', PlaylistDeliveryTask::class) ?? false,
        ];
    }
}
