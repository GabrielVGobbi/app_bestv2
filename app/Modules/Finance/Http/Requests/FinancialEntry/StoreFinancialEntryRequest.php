<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\FinancialEntry;

use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FinancialEntry::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists(Company::class, 'id')],
            'direction' => ['required', Rule::enum(FinancialDirection::class)],
            'counterparty_type' => ['nullable', 'string', Rule::in([Client::class, Supplier::class])],
            'counterparty_id' => ['nullable', 'integer'],
            'originable_type' => ['nullable', 'string', 'max:255'],
            'originable_id' => ['nullable', 'integer'],
            'origin_reference' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', Rule::exists(FinancialCategory::class, 'id')],
            'description' => ['required', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'competence_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'metadata' => ['nullable', 'array'],
            'components' => ['required', 'array', 'min:1'],
            'components.*' => ['required', 'array:code,label,operation,amount,position,metadata'],
            'components.*.code' => ['required', Rule::enum(FinancialAmountComponentCode::class)],
            'components.*.label' => ['nullable', 'string', 'max:255'],
            'components.*.operation' => ['required', Rule::enum(FinancialAmountOperation::class)],
            'components.*.amount' => ['required', 'integer', 'min:1'],
            'components.*.position' => ['nullable', 'integer', 'min:0'],
            'components.*.metadata' => ['nullable', 'array'],
        ];
    }
}
