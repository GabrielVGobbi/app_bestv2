<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use App\Http\Requests\PaginationRequest;
use Carbon\CarbonImmutable;

class FinancialInstallmentIndexRequest extends PaginationRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }

    public function monthStart(): ?CarbonImmutable
    {
        $month = $this->validated('month');

        return is_string($month)
            ? CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth()
            : null;
    }
}
