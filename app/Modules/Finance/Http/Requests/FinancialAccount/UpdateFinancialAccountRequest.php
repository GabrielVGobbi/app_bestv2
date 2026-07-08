<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\FinancialAccount;

use App\Modules\Finance\Models\FinancialAccount;

class UpdateFinancialAccountRequest extends StoreFinancialAccountRequest
{
    public function authorize(): bool
    {
        $financialAccount = $this->route('financial_account');

        return $financialAccount instanceof FinancialAccount
            && ($this->user()?->can('update', $financialAccount) ?? false);
    }
}
