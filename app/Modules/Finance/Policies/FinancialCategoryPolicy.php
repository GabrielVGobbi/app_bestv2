<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Support\FinancialCompanyAccess;

class FinancialCategoryPolicy
{
    public function __construct(private readonly FinancialCompanyAccess $companyAccess) {}

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'finance.categories.view');
    }

    public function view(User $user, FinancialCategory $financialCategory): bool
    {
        return $this->hasPermission($user, 'finance.categories.view')
            && $this->canAccessCompany($user, $financialCategory->company_id);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'finance.categories.create');
    }

    public function update(User $user, FinancialCategory $financialCategory): bool
    {
        return $this->hasPermission($user, 'finance.categories.update')
            && $this->canAccessCompany($user, $financialCategory->company_id);
    }

    public function delete(User $user, FinancialCategory $financialCategory): bool
    {
        return $this->hasPermission($user, 'finance.categories.delete')
            && $this->canAccessCompany($user, $financialCategory->company_id)
            && $financialCategory->canBeDeleted();
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }

    private function canAccessCompany(User $user, ?int $companyId): bool
    {
        return $companyId === null || $this->companyAccess->canAccess($user, $companyId);
    }
}
