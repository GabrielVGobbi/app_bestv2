<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\FinancialEntry;

use App\Modules\Finance\Models\FinancialEntry;
use Illuminate\Foundation\Http\FormRequest;

class CancelFinancialEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('financial_entry');

        return $entry instanceof FinancialEntry
            && ($this->user()?->can('cancel', $entry) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => __('Informe o motivo do cancelamento do lançamento.'),
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
