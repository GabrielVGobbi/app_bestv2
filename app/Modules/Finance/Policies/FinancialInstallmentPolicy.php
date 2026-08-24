<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Support\FinancialCompanyAccess;

/**
 * As filas de contas a pagar e a receber sao o mesmo dado do lancamento visto por
 * outro eixo, entao reaproveitam `finance.entries.view` em vez de criar uma
 * permissao paralela que teria de ser concedida junto na pratica.
 */
class FinancialInstallmentPolicy
{
    public function __construct(private readonly FinancialCompanyAccess $companyAccess) {}

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'finance.entries.view');
    }

    public function view(User $user, FinancialInstallment $financialInstallment): bool
    {
        return $this->hasPermission($user, 'finance.entries.view')
            && $this->companyAccess->canAccess($user, (int) $financialInstallment->company_id);
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }
}
