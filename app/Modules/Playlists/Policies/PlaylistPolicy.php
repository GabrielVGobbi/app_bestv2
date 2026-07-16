<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Policies;

use App\Models\User;
use App\Modules\Playlists\Models\Playlist;

class PlaylistPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'playlists.view');
    }

    public function view(User $user, Playlist $playlist): bool
    {
        return $this->hasPermission($user, 'playlists.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'playlists.create');
    }

    public function update(User $user, Playlist $playlist): bool
    {
        return $this->hasPermission($user, 'playlists.update');
    }

    public function delete(User $user, Playlist $playlist): bool
    {
        return $this->hasPermission($user, 'playlists.delete');
    }

    public function restore(User $user, Playlist $playlist): bool
    {
        return $this->hasPermission($user, 'playlists.restore');
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }
}
