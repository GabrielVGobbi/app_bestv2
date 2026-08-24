<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\FinancialSettlement;

use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountNature;
use App\Modules\Finance\Enums\FinancialAmountOperation;
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
            'gross_amount' => ['required_without:components', 'integer', 'min:1'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'interest_amount' => ['nullable', 'integer', 'min:0'],
            'penalty_amount' => ['nullable', 'integer', 'min:0'],
            'components' => ['nullable', 'array', 'min:1'],
            'components.*' => ['required', 'array:code,nature,label,operation,affects_net,amount,origin,base_amount,rate_basis_points,calculation_method,position,metadata'],
            'components.*.code' => ['required', Rule::enum(FinancialAmountComponentCode::class)],
            'components.*.nature' => ['nullable', Rule::enum(FinancialAmountNature::class)],
            'components.*.label' => ['nullable', 'string', 'max:255'],
            'components.*.operation' => ['nullable', Rule::enum(FinancialAmountOperation::class)],
            'components.*.affects_net' => ['nullable', 'boolean'],
            'components.*.amount' => ['required', 'integer', 'min:1'],
            'components.*.origin' => ['nullable', 'string', 'max:32'],
            'components.*.base_amount' => ['nullable', 'integer', 'min:0'],
            'components.*.rate_basis_points' => ['nullable', 'integer', 'min:0'],
            'components.*.calculation_method' => ['nullable', 'string', 'max:32'],
            'components.*.position' => ['nullable', 'integer', 'min:0'],
            'components.*.metadata' => ['nullable', 'array'],
            'origin' => ['nullable', 'string', 'max:32'],
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
