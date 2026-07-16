<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Policies;

use App\Models\User;
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
        return $user->hasAnyPermission($slug);
    }
}
