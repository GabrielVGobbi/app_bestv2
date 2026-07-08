<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Database\Factories\FinancialInstallmentFactory;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Uploads\Traits\HasUploads;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'company_id',
    'financial_entry_id',
    'status',
    'installment_number',
    'due_date',
    'competence_date',
    'original_amount',
    'settled_amount',
    'balance_amount',
    'currency',
    'settled_at',
    'metadata',
])]
class FinancialInstallment extends Model
{
    use HasFactory, HasUploads, HasUuidIdentifier, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'open',
        'installment_number' => 1,
        'original_amount' => 0,
        'settled_amount' => 0,
        'balance_amount' => 0,
        'currency' => 'BRL',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function financialEntry(): BelongsTo
    {
        return $this->belongsTo(FinancialEntry::class);
    }

    public function settlementAllocations(): HasMany
    {
        return $this->hasMany(FinancialSettlementAllocation::class, 'installment_id');
    }

    public function calculatedStatus(): FinancialEntryStatus
    {
        if (in_array($this->status, [
            FinancialEntryStatus::Canceled,
            FinancialEntryStatus::Reversed,
            FinancialEntryStatus::Renegotiated,
        ], true)) {
            return $this->status;
        }

        if ($this->balance_amount <= 0) {
            return FinancialEntryStatus::Paid;
        }

        if ($this->isOverdue()) {
            return FinancialEntryStatus::Overdue;
        }

        if ($this->settled_amount > 0) {
            return FinancialEntryStatus::PartiallyPaid;
        }

        return FinancialEntryStatus::Open;
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->balance_amount > 0
            && in_array($this->status, [
                FinancialEntryStatus::Open,
                FinancialEntryStatus::Overdue,
                FinancialEntryStatus::PartiallyPaid,
            ], true)
            && $this->due_date->lt(today());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => FinancialEntryStatus::class,
            'due_date' => 'date',
            'competence_date' => 'date',
            'original_amount' => 'integer',
            'settled_amount' => 'integer',
            'balance_amount' => 'integer',
            'metadata' => 'array',
            'settled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return FinancialInstallmentFactory::new();
    }
}
