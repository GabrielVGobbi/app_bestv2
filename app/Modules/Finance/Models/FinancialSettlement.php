<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Database\Factories\FinancialSettlementFactory;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialSettlementStatus;
use App\Modules\Uploads\Traits\HasUploads;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'uuid',
    'company_id',
    'financial_entry_id',
    'direction',
    'settled_at',
    'financial_account_id',
    'payment_method_id',
    'gross_amount',
    'addition_amount',
    'deduction_amount',
    'discount_amount',
    'interest_amount',
    'penalty_amount',
    'net_amount',
    'origin',
    'idempotency_key',
    'idempotency_fingerprint',
    'status',
    'created_by_id',
    'reversed_at',
    'reversed_by_id',
    'reverse_reason',
    'metadata',
])]
class FinancialSettlement extends Model
{
    use HasActivityLog, HasFactory, HasUploads, HasUuidIdentifier;

    protected string $activityLogName = 'finance.settlements';

    /** @var array<string, mixed> */
    protected $attributes = [
        'direction' => 'receivable',
        'discount_amount' => 0,
        'addition_amount' => 0,
        'deduction_amount' => 0,
        'interest_amount' => 0,
        'penalty_amount' => 0,
        'origin' => 'manual',
        'status' => 'posted',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function financialEntry(): BelongsTo
    {
        return $this->belongsTo(FinancialEntry::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FinancialSettlementAllocation::class, 'settlement_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(FinancialSettlementComponent::class, 'settlement_id')
            ->orderBy('position');
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(FinancialMovement::class, 'source');
    }

    public function isPosted(): bool
    {
        return $this->status === FinancialSettlementStatus::Posted;
    }

    public function hasReconciledMovements(): bool
    {
        if ($this->relationLoaded('movements')) {
            return $this->movements->contains(
                fn (FinancialMovement $movement): bool => $movement->reconciled_at !== null
            );
        }

        return $this->movements()
            ->whereNotNull('reconciled_at')
            ->exists();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'direction' => FinancialDirection::class,
            'financial_account_id' => 'integer',
            'settled_at' => 'datetime',
            'gross_amount' => 'integer',
            'addition_amount' => 'integer',
            'deduction_amount' => 'integer',
            'discount_amount' => 'integer',
            'interest_amount' => 'integer',
            'penalty_amount' => 'integer',
            'net_amount' => 'integer',
            'status' => FinancialSettlementStatus::class,
            'reversed_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return FinancialSettlementFactory::new();
    }
}
