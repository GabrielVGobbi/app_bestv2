<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialEntry;

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

    public function upload(User $user, FinancialEntry $financialEntry): bool
    {
        return $this->hasPermission($user, 'finance.entries.view')
            && $this->hasPermission($user, 'finance.attachments.manage');
    }

    public function cancel(User $user, FinancialEntry $financialEntry): bool
    {
        return $this->hasPermission($user, 'finance.entries.cancel')
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
