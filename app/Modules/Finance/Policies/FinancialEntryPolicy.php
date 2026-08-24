<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Support\FinancialCompanyAccess;

class FinancialEntryPolicy
{
    public function __construct(private readonly FinancialCompanyAccess $companyAccess) {}

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'finance.entries.view');
    }

    public function view(User $user, FinancialEntry $financialEntry): bool
    {
        return $this->hasPermission($user, 'finance.entries.view')
            && $this->companyAccess->canAccess($user, (int) $financialEntry->company_id);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'finance.entries.create');
    }

    public function update(User $user, FinancialEntry $financialEntry): bool
    {
        return $this->hasPermission($user, 'finance.entries.update')
            && $this->companyAccess->canAccess($user, (int) $financialEntry->company_id)
            && (int) $financialEntry->settled_amount === 0;
    }

    public function upload(User $user, FinancialEntry $financialEntry): bool
    {
        return $this->hasPermission($user, 'finance.entries.view')
            && $this->companyAccess->canAccess($user, (int) $financialEntry->company_id)
            && $this->hasPermission($user, 'finance.attachments.manage');
    }

    public function cancel(User $user, FinancialEntry $financialEntry): bool
    {
        return $this->hasPermission($user, 'finance.entries.cancel')
            && $this->companyAccess->canAccess($user, (int) $financialEntry->company_id)
            && (int) $financialEntry->settled_amount === 0
            && ! in_array($financialEntry->status, [
                FinancialEntryStatus::Canceled,
                FinancialEntryStatus::Renegotiated,
            ], true);
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }
}
