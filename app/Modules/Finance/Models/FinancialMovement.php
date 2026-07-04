<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Database\Factories\FinancialMovementFactory;
use App\Modules\Finance\Enums\FinancialMovementDirection;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'uuid',
    'company_id',
    'financial_account_id',
    'direction',
    'amount',
    'occurred_at',
    'source_type',
    'source_id',
    'reconciled_at',
    'metadata',
])]
class FinancialMovement extends Model
{
    use HasFactory, HasUuidIdentifier;

    /** @var array<string, mixed> */
    protected $attributes = [
        'direction' => 'inflow',
        'amount' => 0,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'direction' => FinancialMovementDirection::class,
            'amount' => 'integer',
            'occurred_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return FinancialMovementFactory::new();
    }
}
