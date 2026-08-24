<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\ReconciliationMatchStatus;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'company_id',
    'bank_statement_line_id',
    'financial_movement_id',
    'status',
    'confirmed_bank_statement_line_id',
    'confirmed_financial_movement_id',
    'score',
    'reasons',
    'confirmed_by_id',
    'confirmed_at',
    'rejected_by_id',
    'rejected_at',
    'reversed_by_id',
    'reversed_at',
    'reverse_reason',
])]
class ReconciliationMatch extends Model
{
    use HasActivityLog, HasUuidIdentifier;

    protected string $activityLogName = 'finance.reconciliation';

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'suggested',
        'score' => 0,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function bankStatementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class);
    }

    public function financialMovement(): BelongsTo
    {
        return $this->belongsTo(FinancialMovement::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ReconciliationMatchStatus::class,
            'confirmed_bank_statement_line_id' => 'integer',
            'confirmed_financial_movement_id' => 'integer',
            'company_id' => 'integer',
            'bank_statement_line_id' => 'integer',
            'financial_movement_id' => 'integer',
            'score' => 'integer',
            'reasons' => 'array',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'reversed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
