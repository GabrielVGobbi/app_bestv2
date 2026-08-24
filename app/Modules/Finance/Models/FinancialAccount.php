<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Database\Factories\FinancialAccountFactory;
use App\Modules\Finance\Enums\FinancialAccountType;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'company_id',
    'name',
    'type',
    'institution_name',
    'agency_number',
    'account_number',
    'account_digit',
    'document_number',
    'opening_balance_amount',
    'current_balance_amount',
    'balance_date',
    'currency',
    'active',
    'allows_negative_balance',
    'metadata',
])]
class FinancialAccount extends Model
{
    use Exportable, HasActivityLog, HasFactory, HasUuidIdentifier, Searchable;

    protected string $activityLogName = 'finance.accounts';

    /** @var array<string, string> */
    protected array $exportable = [
        'name' => 'Nome',
        'type' => 'Tipo',
        'institution_name' => 'Banco',
        'agency_number' => 'Agencia',
        'account_number' => 'Conta',
        'current_balance_amount' => 'Saldo atual',
        'currency' => 'Moeda',
        'active' => 'Ativa',
        'created_at' => 'Criado em',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['name', 'institution_name', 'agency_number', 'account_number', 'document_number'];

    /** @var array<int, string> */
    protected array $filterable = ['company_id', 'name', 'type', 'active', 'currency', 'created_at', 'updated_at'];

    /** @var array<int, string> */
    protected array $sortable = ['id', 'name', 'type', 'institution_name', 'current_balance_amount', 'active', 'created_at', 'updated_at'];

    protected string $defaultSortColumn = 'name';

    protected string $defaultSortDirection = 'asc';

    /** @var array<string, mixed> */
    protected $attributes = [
        'type' => 'bank_account',
        'opening_balance_amount' => 0,
        'current_balance_amount' => 0,
        'currency' => 'BRL',
        'active' => true,
        'allows_negative_balance' => false,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(FinancialSettlement::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FinancialMovement::class);
    }

    public function bankStatementImports(): HasMany
    {
        return $this->hasMany(BankStatementImport::class);
    }

    public function bankStatementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        if ($companyId === null) {
            return $query;
        }

        return $query->where('company_id', $companyId);
    }

    public function typeLabel(): string
    {
        return match ($this->type?->value ?? $this->type) {
            'bank_account' => 'Conta bancaria',
            'cash' => 'Caixa',
            'wallet' => 'Carteira',
            'payment_gateway' => 'Gateway de pagamento',
            default => 'Conta financeira',
        };
    }

    public function displayName(): string
    {
        if ($this->type === FinancialAccountType::BankAccount && filled($this->institution_name)) {
            return "{$this->name} - {$this->institution_name}";
        }

        return $this->name;
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'type' => FinancialAccountType::class,
            'company_id' => 'integer',
            'opening_balance_amount' => 'integer',
            'current_balance_amount' => 'integer',
            'balance_date' => 'date',
            'active' => 'boolean',
            'allows_negative_balance' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return FinancialAccountFactory::new();
    }
}
