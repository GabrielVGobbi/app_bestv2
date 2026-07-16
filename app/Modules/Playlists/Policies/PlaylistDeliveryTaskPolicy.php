<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Policies;

use App\Models\User;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;

class PlaylistDeliveryTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'playlist-delivery-tasks.view');
    }

    public function view(User $user, PlaylistDeliveryTask $playlistDeliveryTask): bool
    {
        return $this->hasPermission($user, 'playlist-delivery-tasks.view');
    }

    public function complete(User $user, PlaylistDeliveryTask $playlistDeliveryTask): bool
    {
        return $this->hasPermission($user, 'playlist-delivery-tasks.complete');
    }

    public function changeTrack(User $user): bool
    {
        return $this->hasPermission($user, 'playlist-delivery-tasks.change-track');
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }
}
