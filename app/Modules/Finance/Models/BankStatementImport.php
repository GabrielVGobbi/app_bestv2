<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\BankStatementImportStatus;
use App\Modules\Uploads\Traits\HasUploads;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'company_id',
    'financial_account_id',
    'created_by_id',
    'source',
    'status',
    'original_name',
    'file_hash',
    'line_count',
    'duplicate_count',
    'metadata',
])]
class BankStatementImport extends Model
{
    use HasActivityLog, HasUploads, HasUuidIdentifier;

    protected string $activityLogName = 'finance.statement-imports';

    /** @var array<string, mixed> */
    protected $attributes = [
        'source' => 'csv',
        'status' => 'processed',
        'line_count' => 0,
        'duplicate_count' => 0,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => BankStatementImportStatus::class,
            'company_id' => 'integer',
            'financial_account_id' => 'integer',
            'created_by_id' => 'integer',
            'line_count' => 'integer',
            'duplicate_count' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
