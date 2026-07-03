<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Policies;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use Illuminate\Database\Eloquent\Builder;

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
        $superRole = config('permissions.super_role', 'developer');

        if ($user->roles()->where('slug', $superRole)->exists()) {
            return true;
        }

        if ($user->permissions()->where('slug', $slug)->exists()) {
            return true;
        }

        return Permission::query()
            ->where('slug', $slug)
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('roles.id', $user->roles()->select('roles.id')))
            ->exists();
    }
}
