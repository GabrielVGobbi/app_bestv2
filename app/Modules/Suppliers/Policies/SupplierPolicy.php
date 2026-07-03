<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Policies;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Suppliers\Models\Supplier;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->hasPermission($user, 'suppliers.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->hasPermission($user, 'suppliers.update');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->hasPermission($user, 'suppliers.delete') && $supplier->canBeDeleted();
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
