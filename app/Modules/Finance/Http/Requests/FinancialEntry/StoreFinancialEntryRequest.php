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
use App\Modules\Finance\Support\FinancialCompanyAccess;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFinancialEntryRequest extends FormRequest
{
    public function authorize(FinancialCompanyAccess $companyAccess): bool
    {
        $user = $this->user();

        if (! $user?->can('create', FinancialEntry::class)) {
            return false;
        }

        $companyId = $this->input('company_id');

        return ! is_numeric($companyId)
            || $companyAccess->canAccess($user, (int) $companyId);
    }

    /**
     * A origem (`originable_type`/`originable_id`) nao e aceita pela API publica.
     * Ela e definida apenas por Actions internas, como CreateReceivableFromSale,
     * para que ninguem possa forjar a origem de um lancamento e sequestrar o
     * indice unico `financial_entries_origin_unique`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists(Company::class, 'id')],
            'direction' => ['required', Rule::enum(FinancialDirection::class)],
            'counterparty_type' => ['required', 'string', Rule::in([Client::class, Supplier::class])],
            'counterparty_id' => ['required', 'integer'],
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
            'installments' => ['nullable', 'array', 'min:1'],
            'installments.*' => ['required', 'array:installment_number,due_date,competence_date,amount,metadata'],
            'installments.*.installment_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'installments.*.due_date' => ['nullable', 'date'],
            'installments.*.competence_date' => ['nullable', 'date'],
            'installments.*.amount' => ['required', 'integer', 'min:1'],
            'installments.*.metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $components = $this->input('components', []);

                if (! is_array($components)) {
                    return;
                }

                foreach (array_values($components) as $index => $component) {
                    if (! is_array($component)) {
                        continue;
                    }

                    $code = is_string($component['code'] ?? null)
                        ? FinancialAmountComponentCode::tryFrom($component['code'])
                        : null;
                    $operation = is_string($component['operation'] ?? null)
                        ? FinancialAmountOperation::tryFrom($component['operation'])
                        : null;

                    if (! $code || ! $operation) {
                        continue;
                    }

                    $fixedOperation = $code->fixedOperation();

                    if ($fixedOperation && $operation !== $fixedOperation) {
                        $validator->errors()->add(
                            "components.{$index}.operation",
                            __('A operação informada não é permitida para este tipo de ajuste.'),
                        );
                    }

                    if ($code->requiresDescription() && blank($component['label'] ?? null)) {
                        $validator->errors()->add(
                            "components.{$index}.label",
                            __('Informe uma descrição para o ajuste do tipo Outro.'),
                        );
                    }
                }
            },
        ];
    }
}
