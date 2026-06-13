<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Policies;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Models\Top;

class TopPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'playlist-tops.view');
    }

    public function view(User $user, Top $top): bool
    {
        return $this->hasPermission($user, 'playlist-tops.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'playlist-tops.create');
    }

    public function update(User $user, Top $top): bool
    {
        return $this->hasPermission($user, 'playlist-tops.update');
    }

    public function delete(User $user, Top $top): bool
    {
        return $this->hasPermission($user, 'playlist-tops.delete');
    }

    public function restore(User $user, Top $top): bool
    {
        return $this->hasPermission($user, 'playlist-tops.restore');
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
