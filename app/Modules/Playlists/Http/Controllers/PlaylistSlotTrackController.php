<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Playlists\Actions\DeliveryTasks\UpdatePlaylistSlotTrack;
use App\Modules\Playlists\Http\Requests\UpdatePlaylistSlotTrackRequest;
use App\Modules\Playlists\Models\PlaylistSlot;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PlaylistSlotTrackController extends Controller
{
    public function update(
        UpdatePlaylistSlotTrackRequest $request,
        PlaylistSlot $playlistSlot,
        UpdatePlaylistSlotTrack $updatePlaylistSlotTrack,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $updatePlaylistSlotTrack->handle($playlistSlot, $request->validated(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Música da entrega atualizada.')]);

        return back();
    }
}
