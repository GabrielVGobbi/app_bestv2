<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Concerns\FiltersCounterparty;
use App\Modules\Finance\Database\Factories\FinancialInstallmentFactory;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Uploads\Traits\HasUploads;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Parcela financeira: o vencimento real de um lancamento.
 *
 * E a fonte operacional do fluxo de caixa. Direcao, contraparte e categoria sao
 * denormalizadas do lancamento para que as filas de contas a pagar e a receber
 * filtrem, ordenem e exportem sem depender de `whereHas`.
 */
#[Fillable([
    'uuid',
    'company_id',
    'financial_entry_id',
    'status',
    'direction',
    'counterparty_type',
    'counterparty_id',
    'category_id',
    'installment_number',
    'description',
    'due_date',
    'competence_date',
    'original_amount',
    'settled_amount',
    'balance_amount',
    'discount_amount',
    'interest_amount',
    'penalty_amount',
    'currency',
    'settled_at',
    'metadata',
])]
class FinancialInstallment extends Model
{
    use Exportable, HasFactory, HasUploads, HasUuidIdentifier, SoftDeletes;
    use FiltersCounterparty, Searchable {
        Searchable::applyApiFilterOperator as protected applyBaseApiFilterOperator;
        Searchable::scopeApiSort as protected applySearchableApiSort;
        FiltersCounterparty::applyApiFilterOperator as protected applyFinanceApiFilterOperator;
        FiltersCounterparty::applyApiFilterOperator insteadof Searchable;
    }

    /** @var array<string, string> */
    protected array $exportable = [
        'description' => 'Descricao',
        'direction' => 'Direcao',
        'status' => 'Status',
        'installment_number' => 'Parcela',
        'due_date' => 'Vencimento',
        'competence_date' => 'Competencia',
        'original_amount' => 'Valor da parcela',
        'settled_amount' => 'Liquidado',
        'balance_amount' => 'Saldo',
        'created_at' => 'Criado em',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['uuid', 'description'];

    /**
     * `export_direction` e um alias interno usado para a exportacao respeitar a
     * aba ativa sem expor `direction` como filtro comum da API.
     *
     * @var array<int, string>
     */
    protected array $filterable = [
        'uuid',
        'company_id',
        'export_direction',
        'status',
        'category_id',
        'counterparty',
        'counterparty_type',
        'counterparty_id',
        'financial_entry_id',
        'installment_number',
        'due_date',
        'competence_date',
        'settled_at',
        'created_at',
        'updated_at',
    ];

    /** @var array<int, string> */
    protected array $sortable = [
        'id',
        'status',
        'installment_number',
        'due_date',
        'competence_date',
        'original_amount',
        'settled_amount',
        'balance_amount',
        'created_at',
        'updated_at',
    ];

    /** @var array<int, string> */
    protected array $apiIncludes = ['financialEntry', 'category', 'counterparty'];

    /**
     * A fila trabalha pelo vencimento mais proximo, nao pelo registro mais novo.
     */
    protected string $defaultSortColumn = 'due_date';

    protected string $defaultSortDirection = 'asc';

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'open',
        'installment_number' => 1,
        'original_amount' => 0,
        'settled_amount' => 0,
        'balance_amount' => 0,
        'discount_amount' => 0,
        'interest_amount' => 0,
        'penalty_amount' => 0,
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

    public function counterparty(): MorphTo
    {
        return $this->morphTo();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    public function settlementAllocations(): HasMany
    {
        return $this->hasMany(FinancialSettlementAllocation::class, 'installment_id');
    }

    /**
     * Parcelas que ainda contam para o financeiro, ou seja, fora dos estados terminais.
     *
     * @param  Builder<FinancialInstallment>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotIn('status', self::terminalStatuses());
    }

    /**
     * Parcelas ativas com saldo a liquidar.
     *
     * @param  Builder<FinancialInstallment>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->active()->where('balance_amount', '>', 0);
    }

    /**
     * Definicao unica de vencido em SQL, espelhando `isOverdue()`.
     *
     * Dashboard, agenda, relatorios e filas precisam usar este scope. Enquanto
     * cada tela respondia a essa pergunta do seu jeito, os numeros divergiam.
     *
     * @param  Builder<FinancialInstallment>  $query
     */
    public function scopeOverdue(Builder $query, ?string $today = null): void
    {
        $query->open()
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today ?? today()->toDateString());
    }

    /**
     * @param  Builder<FinancialInstallment>  $query
     */
    public function scopeDirection(Builder $query, FinancialDirection|string $direction): void
    {
        $query->where('direction', $direction instanceof FinancialDirection ? $direction->value : $direction);
    }

    protected function applyApiFilterOperator(Builder $query, string $column, string $operator, mixed $value): void
    {
        if ($column === 'export_direction') {
            $this->applyBaseApiFilterOperator($query, 'direction', $operator, $value);

            return;
        }

        $this->applyFinanceApiFilterOperator($query, $column, $operator, $value);
    }

    /**
     * Mantem vencimentos sem data no fim e pagina de forma estavel.
     *
     * @param  Builder<FinancialInstallment>  $query
     * @return Builder<FinancialInstallment>
     */
    public function scopeApiSort(Builder $query, ?string $column, ?string $direction = null): Builder
    {
        if (filled($column)) {
            return $this->applySearchableApiSort($query, $column, $direction)
                ->orderBy($this->qualifyColumn('id'));
        }

        return $query
            ->orderByRaw($this->qualifyColumn('due_date').' IS NULL')
            ->orderBy($this->qualifyColumn('due_date'))
            ->orderBy($this->qualifyColumn('id'));
    }

    /**
     * @return array<int, FinancialEntryStatus>
     */
    public static function terminalStatuses(): array
    {
        return [
            FinancialEntryStatus::Canceled,
            FinancialEntryStatus::Reversed,
            FinancialEntryStatus::Renegotiated,
        ];
    }

    public function calculatedStatus(): FinancialEntryStatus
    {
        if (in_array($this->status, self::terminalStatuses(), true)) {
            return $this->status;
        }

        if ($this->balance_amount <= 0) {
            return FinancialEntryStatus::Paid;
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

    /**
     * Rotulo de posicao da parcela, no formato "2/3".
     *
     * Depende de `installments_count` ja carregado no lancamento. Sem a relacao
     * carregada devolve so o numero da parcela, em vez de disparar uma consulta
     * por linha (ou violar o `preventLazyLoading`).
     */
    public function positionLabel(): string
    {
        $total = $this->relationLoaded('financialEntry')
            ? $this->financialEntry?->installments_count
            : null;

        return $total ? "{$this->installment_number}/{$total}" : (string) $this->installment_number;
    }

    /**
     * Nome legivel da contraparte, sem tocar na relacao quando ela nao foi carregada.
     */
    public function counterpartyLabel(): ?string
    {
        if (! $this->relationLoaded('counterparty') || $this->counterparty === null) {
            return null;
        }

        foreach (['name', 'company_name', 'title', 'uuid'] as $attribute) {
            if (filled($this->counterparty->{$attribute} ?? null)) {
                return (string) $this->counterparty->{$attribute};
            }
        }

        return null;
    }

    public function directionLabel(): string
    {
        return match ($this->direction) {
            FinancialDirection::Receivable => 'A receber',
            FinancialDirection::Payable => 'A pagar',
            default => 'Financeiro',
        };
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => FinancialEntryStatus::class,
            'direction' => FinancialDirection::class,
            'category_id' => 'integer',
            'due_date' => 'date',
            'competence_date' => 'date',
            'original_amount' => 'integer',
            'settled_amount' => 'integer',
            'balance_amount' => 'integer',
            'discount_amount' => 'integer',
            'interest_amount' => 'integer',
            'penalty_amount' => 'integer',
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
