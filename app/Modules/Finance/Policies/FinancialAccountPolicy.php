<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Support\FinancialCompanyAccess;

class FinancialAccountPolicy
{
    public function __construct(private readonly FinancialCompanyAccess $companyAccess) {}

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'finance.accounts.view');
    }

    public function view(User $user, FinancialAccount $financialAccount): bool
    {
        return $this->hasPermission($user, 'finance.accounts.view')
            && $this->companyAccess->canAccess($user, (int) $financialAccount->company_id);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'finance.accounts.create');
    }

    public function update(User $user, FinancialAccount $financialAccount): bool
    {
        return $this->hasPermission($user, 'finance.accounts.update')
            && $this->companyAccess->canAccess($user, (int) $financialAccount->company_id);
    }

    public function delete(User $user, FinancialAccount $financialAccount): bool
    {
        return $this->hasPermission($user, 'finance.accounts.archive')
            && $this->companyAccess->canAccess($user, (int) $financialAccount->company_id);
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }
}
