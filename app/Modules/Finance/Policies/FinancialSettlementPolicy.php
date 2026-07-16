<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Enums\FinancialSettlementStatus;
use App\Modules\Finance\Models\FinancialSettlement;

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
            && $financialSettlement->status === FinancialSettlementStatus::Posted
            && ! $financialSettlement->hasReconciledMovements();
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }
}
