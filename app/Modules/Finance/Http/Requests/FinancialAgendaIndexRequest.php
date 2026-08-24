<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Enums\FinancialDirection;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialAgendaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'direction' => [
                'nullable',
                Rule::in([
                    'all',
                    FinancialDirection::Receivable->value,
                    FinancialDirection::Payable->value,
                ]),
            ],
        ];
    }

    public function month(): string
    {
        return $this->validated('month') ?? today()->format('Y-m');
    }

    public function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->month())->startOfMonth();
    }

    public function agendaDirection(): string
    {
        return $this->validated('direction') ?? 'all';
    }
}
