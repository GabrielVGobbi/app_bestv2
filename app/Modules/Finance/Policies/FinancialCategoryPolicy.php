<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Permissions\Models\Permission;

class FinancialCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'finance.categories.view');
    }

    public function view(User $user, FinancialCategory $financialCategory): bool
    {
        return $this->hasPermission($user, 'finance.categories.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'finance.categories.create');
    }

    public function update(User $user, FinancialCategory $financialCategory): bool
    {
        return $this->hasPermission($user, 'finance.categories.update');
    }

    public function delete(User $user, FinancialCategory $financialCategory): bool
    {
        return $this->hasPermission($user, 'finance.categories.delete') && $financialCategory->canBeDeleted();
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
