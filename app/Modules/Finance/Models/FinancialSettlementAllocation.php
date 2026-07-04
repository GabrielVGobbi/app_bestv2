<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Companies\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'settlement_id',
    'installment_id',
    'amount',
    'balance_before',
    'balance_after',
])]
class FinancialSettlementAllocation extends Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(FinancialSettlement::class, 'settlement_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(FinancialInstallment::class, 'installment_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
