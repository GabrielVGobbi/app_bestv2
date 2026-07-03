<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Database\Factories\FinancialInstallmentFactory;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'company_id',
    'financial_entry_id',
    'status',
    'installment_number',
    'due_date',
    'original_amount',
    'settled_amount',
    'balance_amount',
    'currency',
    'metadata',
])]
class FinancialInstallment extends Model
{
    use HasFactory, HasUuidIdentifier, SoftDeletes;

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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => FinancialEntryStatus::class,
            'due_date' => 'date',
            'original_amount' => 'integer',
            'settled_amount' => 'integer',
            'balance_amount' => 'integer',
            'metadata' => 'array',
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
