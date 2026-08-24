<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\FinancialAccount;

use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialAccountRequest extends FormRequest
{
    public function authorize(FinancialCompanyAccess $companyAccess): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->can('create', FinancialAccount::class)
            && $companyAccess->canAccess($user, $this->integer('company_id'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(FinancialAccountType::class)],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'agency_number' => ['nullable', 'string', 'max:32'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'account_digit' => ['nullable', 'string', 'max:16'],
            'document_number' => ['nullable', 'string', 'max:32'],
            'opening_balance_amount' => ['nullable', 'integer'],
            'current_balance_amount' => ['nullable', 'integer'],
            'balance_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'active' => ['nullable', 'boolean'],
            'allows_negative_balance' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);
        $openingBalance = (int) ($validated['opening_balance_amount'] ?? 0);

        $validated['opening_balance_amount'] = $openingBalance;
        $validated['current_balance_amount'] = array_key_exists('current_balance_amount', $validated)
            ? (int) $validated['current_balance_amount']
            : $openingBalance;
        $validated['currency'] = strtoupper((string) ($validated['currency'] ?? 'BRL'));
        $validated['active'] = $this->boolean('active', true);
        $validated['allows_negative_balance'] = $this->boolean('allows_negative_balance', false);
        $validated['metadata'] = $validated['metadata'] ?? null;

        return $validated;
    }
}
