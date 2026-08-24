<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\BankStatementImport;

use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankStatementImportRequest extends FormRequest
{
    public function authorize(FinancialCompanyAccess $companyAccess): bool
    {
        $user = $this->user();

        if (! $user?->can('finance.reconciliation.manage')) {
            return false;
        }

        $accountId = $this->input('financial_account_id');

        if (! is_numeric($accountId)) {
            return true;
        }

        $companyId = FinancialAccount::query()->whereKey((int) $accountId)->value('company_id');

        return $companyId === null || $companyAccess->canAccess($user, (int) $companyId);
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
