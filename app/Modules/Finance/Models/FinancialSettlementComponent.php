<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Database\Factories\FinancialSettlementComponentFactory;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountNature;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'company_id',
    'settlement_id',
    'code',
    'nature',
    'label',
    'operation',
    'affects_net',
    'amount',
    'origin',
    'base_amount',
    'rate_basis_points',
    'calculation_method',
    'position',
    'metadata',
])]
class FinancialSettlementComponent extends Model
{
    use HasFactory, HasUuidIdentifier;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(FinancialSettlement::class, 'settlement_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'code' => FinancialAmountComponentCode::class,
            'nature' => FinancialAmountNature::class,
            'operation' => FinancialAmountOperation::class,
            'affects_net' => 'boolean',
            'amount' => 'integer',
            'base_amount' => 'integer',
            'rate_basis_points' => 'integer',
            'position' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): Factory
    {
        return FinancialSettlementComponentFactory::new();
    }
}
