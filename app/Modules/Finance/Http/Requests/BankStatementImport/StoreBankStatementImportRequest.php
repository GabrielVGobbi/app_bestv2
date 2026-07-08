<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\BankStatementImport;

use App\Modules\Finance\Models\FinancialAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankStatementImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finance.reconciliation.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'financial_account_id' => [
                'required',
                'integer',
                Rule::exists(FinancialAccount::class, 'id')->where('active', true),
            ],
            'source' => ['required', 'string', Rule::in(['csv', 'ofx'])],
            'file' => ['required', 'file', 'max:10240'],
        ];
    }
}
