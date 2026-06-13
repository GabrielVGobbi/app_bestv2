<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Policies;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Models\PlaylistTop;

class PlaylistTopPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'playlist-tops.view');
    }

    public function view(User $user, PlaylistTop $playlistTop): bool
    {
        return $this->hasPermission($user, 'playlist-tops.view');
    }

    public function attach(User $user): bool
    {
        return $this->hasPermission($user, 'playlist-tops.attach');
    }

    public function configure(User $user, PlaylistTop $playlistTop): bool
    {
        return $this->hasPermission($user, 'playlist-tops.configure');
    }

    public function detach(User $user, PlaylistTop $playlistTop): bool
    {
        return $this->hasPermission($user, 'playlist-tops.detach');
    }

    private function hasPermission(User $user, string $slug): bool
    {
        $superRole = config('permissions.super_role', 'developer');

        if ($user->roles()->where('slug', $superRole)->exists()) {
            return true;
        }

        if ($user->permissions()->where('slug', $slug)->exists()) {
            return true;
        }

        return Permission::query()
            ->where('slug', $slug)
            ->whereHas('roles', fn ($roles) => $roles->whereIn('roles.id', $user->roles()->select('roles.id')))
            ->exists();
    }
}
