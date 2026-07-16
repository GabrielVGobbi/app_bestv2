<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\FinancialCategory;

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
        return $user->hasAnyPermission($slug);
    }
}
