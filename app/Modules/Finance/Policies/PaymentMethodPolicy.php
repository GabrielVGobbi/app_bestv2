<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Support\FinancialCompanyAccess;

class PaymentMethodPolicy
{
    public function __construct(private readonly FinancialCompanyAccess $companyAccess) {}

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'finance.payment-methods.view');
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->hasPermission($user, 'finance.payment-methods.view')
            && $this->canAccessCompany($user, $paymentMethod->company_id);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'finance.payment-methods.create');
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->hasPermission($user, 'finance.payment-methods.update')
            && $this->canAccessCompany($user, $paymentMethod->company_id);
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->hasPermission($user, 'finance.payment-methods.delete')
            && $this->canAccessCompany($user, $paymentMethod->company_id);
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
