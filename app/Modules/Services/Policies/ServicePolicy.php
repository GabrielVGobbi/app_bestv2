<?php

declare(strict_types=1);

namespace App\Modules\Services\Policies;

use App\Models\User;
use App\Modules\Services\Models\Service;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'services.view');
    }

    public function view(User $user, Service $service): bool
    {
        return $this->hasPermission($user, 'services.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'services.create');
    }

    public function update(User $user, Service $service): bool
    {
        return $this->hasPermission($user, 'services.update');
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->hasPermission($user, 'services.delete');
    }

    public function restore(User $user, Service $service): bool
    {
        return $this->hasPermission($user, 'services.restore');
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }
}
