<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Playlists\Actions\CancelPlaylistSlot;
use App\Modules\Playlists\Actions\CheckPlaylistSlotAvailability;
use App\Modules\Playlists\Actions\SchedulePlaylistSlot;
use App\Modules\Playlists\Http\Requests\SchedulePlaylistSlotRequest;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Playlists\Models\PlaylistTop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlaylistSlotController extends Controller
{
    public function availability(Request $request, PlaylistTop $playlistTop, CheckPlaylistSlotAvailability $checkPlaylistSlotAvailability): JsonResponse
    {
        abort_unless($this->hasPlaylistSlotPermission($request->user(), 'playlist-slots.availability.view'), 403);

        $validated = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);

        return response()->json($checkPlaylistSlotAvailability->handle(
            playlistTop: $playlistTop,
            startsOn: $validated['starts_on'],
            endsOn: $validated['ends_on'],
        ));
    }

    public function schedule(SchedulePlaylistSlotRequest $request, PlaylistSlot $playlistSlot, SchedulePlaylistSlot $schedulePlaylistSlot): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $schedulePlaylistSlot->handle(
            playlistSlot: $playlistSlot,
            startsOn: $request->validated('starts_on'),
            endsOn: $request->validated('ends_on'),
            user: $user,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vaga agendada.')]);

        return back();
    }

    public function cancel(Request $request, PlaylistSlot $playlistSlot, CancelPlaylistSlot $cancelPlaylistSlot): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->hasPlaylistSlotPermission($user, 'playlist-slots.cancel'), 403);

        $validated = $request->validate([
            'status_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $cancelPlaylistSlot->handle($playlistSlot, $user, $validated['status_reason'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vaga cancelada.')]);

        return back();
    }

    private function hasPlaylistSlotPermission(?User $user, string $permission): bool
    {
        return $user instanceof User && $user->hasAnyPermission($permission);
    }
}
