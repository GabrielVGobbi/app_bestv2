<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Reconciliation;

use Illuminate\Foundation\Http\FormRequest;

class ReverseReconciliationMatchRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
