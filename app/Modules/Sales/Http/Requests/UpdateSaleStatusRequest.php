<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests;

use App\Modules\Sales\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSaleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sale = $this->route('sale');
        $ability = str($this->route()?->getName() ?? '')
            ->after('sales.')
            ->toString();

        return $sale instanceof Sale && ($this->user()?->can($ability, $sale) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status_reason' => ['nullable', 'string', 'max:1000'],
            'status_confirmed' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status_confirmed' => $this->boolean('status_confirmed'),
        ]);
    }

    public function statusReason(): ?string
    {
        $reason = $this->validated('status_reason');

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    public function statusConfirmed(): bool
    {
        return (bool) $this->validated('status_confirmed', false);
    }
}
