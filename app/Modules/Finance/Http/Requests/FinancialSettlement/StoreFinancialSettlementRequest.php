<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\FinancialSettlement;

use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialSettlement;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialSettlementRequest extends FormRequest
{
    public function authorize(FinancialCompanyAccess $companyAccess): bool
    {
        $user = $this->user();
        $installment = $this->route('financial_installment');

        return $user?->can('create', FinancialSettlement::class) === true
            && $installment instanceof FinancialInstallment
            && $companyAccess->canAccess($user, (int) $installment->company_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settled_at' => ['nullable', 'date'],
            'financial_account_id' => ['required', 'integer', Rule::exists(FinancialAccount::class, 'id')],
            'payment_method_id' => ['nullable', 'integer', Rule::exists(PaymentMethod::class, 'id')],
            'gross_amount' => ['required', 'integer', 'min:1'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'interest_amount' => ['nullable', 'integer', 'min:0'],
            'penalty_amount' => ['nullable', 'integer', 'min:0'],
            'origin' => ['nullable', 'string', 'max:32'],
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
