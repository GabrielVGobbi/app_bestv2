<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\FinancialAccount;

class FinancialAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'finance.accounts.view');
    }

    public function view(User $user, FinancialAccount $financialAccount): bool
    {
        return $this->hasPermission($user, 'finance.accounts.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'finance.accounts.create');
    }

    public function update(User $user, FinancialAccount $financialAccount): bool
    {
        return $this->hasPermission($user, 'finance.accounts.update');
    }

    public function delete(User $user, FinancialAccount $financialAccount): bool
    {
        return $this->hasPermission($user, 'finance.accounts.archive');
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }
}
