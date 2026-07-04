<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\FinancialSettlement;

use App\Modules\Finance\Models\FinancialSettlement;
use Illuminate\Foundation\Http\FormRequest;

class ReverseFinancialSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var FinancialSettlement|null $settlement */
        $settlement = $this->route('financial_settlement');

        return $settlement !== null
            && ($this->user()?->can('reverse', $settlement) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
