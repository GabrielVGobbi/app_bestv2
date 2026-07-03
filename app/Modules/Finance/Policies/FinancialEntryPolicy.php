<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Permissions\Models\Permission;

class FinancialEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'finance.entries.view');
    }

    public function view(User $user, FinancialEntry $financialEntry): bool
    {
        return $this->hasPermission($user, 'finance.entries.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'finance.entries.create');
    }

    public function update(User $user, FinancialEntry $financialEntry): bool
    {
        return $this->hasPermission($user, 'finance.entries.update')
            && (int) $financialEntry->settled_amount === 0;
    }

    public function cancel(User $user, FinancialEntry $financialEntry): bool
    {
        return $this->hasPermission($user, 'finance.entries.cancel')
            && (int) $financialEntry->settled_amount === 0;
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
