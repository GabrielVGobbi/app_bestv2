<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\FinancialAccount;

use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Support\FinancialCompanyAccess;

class UpdateFinancialAccountRequest extends StoreFinancialAccountRequest
{
    public function authorize(FinancialCompanyAccess $companyAccess): bool
    {
        $financialAccount = $this->route('financial_account');
        $user = $this->user();

        return $financialAccount instanceof FinancialAccount
            && $user !== null
            && $user->can('update', $financialAccount)
            && $companyAccess->canAccess($user, $this->integer('company_id'));
    }
}
