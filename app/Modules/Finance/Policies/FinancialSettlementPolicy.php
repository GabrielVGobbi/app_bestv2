<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Enums\FinancialSettlementStatus;
use App\Modules\Finance\Models\FinancialSettlement;
use App\Modules\Permissions\Models\Permission;

class FinancialSettlementPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'finance.entries.view');
    }

    public function view(User $user, FinancialSettlement $financialSettlement): bool
    {
        return $this->hasPermission($user, 'finance.entries.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'finance.settlements.create');
    }

    public function reverse(User $user, FinancialSettlement $financialSettlement): bool
    {
        return $this->hasPermission($user, 'finance.settlements.reverse')
            && $financialSettlement->status === FinancialSettlementStatus::Posted;
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
