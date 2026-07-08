<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\BankStatementLineStatus;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'bank_statement_import_id',
    'company_id',
    'financial_account_id',
    'status',
    'posted_at',
    'description',
    'amount',
    'reference',
    'line_hash',
    'raw_payload',
    'reconciled_at',
])]
class BankStatementLine extends Model
{
    use HasActivityLog, HasUuidIdentifier;

    protected string $activityLogName = 'finance.statement-lines';

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ReconciliationMatch::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => BankStatementLineStatus::class,
            'company_id' => 'integer',
            'financial_account_id' => 'integer',
            'bank_statement_import_id' => 'integer',
            'posted_at' => 'date',
            'amount' => 'integer',
            'raw_payload' => 'array',
            'reconciled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
