<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Database\Factories\FinancialEntryFactory;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Timeline\Contracts\TimelineSubject;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'company_id',
    'created_by_id',
    'direction',
    'status',
    'counterparty_type',
    'counterparty_id',
    'originable_type',
    'originable_id',
    'origin_reference',
    'category_id',
    'description',
    'issue_date',
    'competence_date',
    'due_date',
    'gross_amount',
    'net_amount',
    'settled_amount',
    'balance_amount',
    'currency',
    'metadata',
])]
class FinancialEntry extends Model implements TimelineSubject
{
    use HasActivityLog, HasFactory, HasUuidIdentifier, SoftDeletes;

    protected string $activityLogName = 'finance.entries';

    /** @var array<string, mixed> */
    protected $attributes = [
        'direction' => 'receivable',
        'status' => 'open',
        'gross_amount' => 0,
        'net_amount' => 0,
        'settled_amount' => 0,
        'balance_amount' => 0,
        'currency' => 'BRL',
    ];

    public static function timelineType(): string
    {
        return 'financial-entry';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function counterparty(): MorphTo
    {
        return $this->morphTo();
    }

    public function originable(): MorphTo
    {
        return $this->morphTo();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    public function amountComponents(): MorphMany
    {
        return $this->morphMany(FinancialAmountComponent::class, 'amountable')
            ->orderBy('position');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(FinancialInstallment::class);
    }

    public function statusEnumClass(): string
    {
        return FinancialEntryStatus::class;
    }

    public function isManualOrigin(): bool
    {
        return $this->originable_type === null && $this->originable_id === null;
    }

    public function originKind(): string
    {
        if ($this->isManualOrigin()) {
            return 'manual';
        }

        return str($this->originable_type)
            ->classBasename()
            ->snake()
            ->value();
    }

    public function originLabel(): string
    {
        return match ($this->originKind()) {
            'manual' => 'Manual',
            'sale' => 'Venda',
            'fiscal_document' => 'Nota fiscal',
            'purchase' => 'Compra',
            'contract' => 'Contrato',
            'gateway' => 'Gateway',
            default => str($this->originKind())->replace('_', ' ')->headline()->value(),
        };
    }

    public function originDisplayName(): string
    {
        if ($this->isManualOrigin()) {
            return $this->origin_reference ?: 'Lancamento manual';
        }

        $origin = $this->originable;

        if ($origin === null) {
            return $this->origin_reference ?: $this->originLabel();
        }

        foreach (['document_number', 'number', 'code', 'name', 'title', 'uuid'] as $attribute) {
            if (filled($origin->{$attribute} ?? null)) {
                return (string) $origin->{$attribute};
            }
        }

        return $this->origin_reference ?: $this->originLabel();
    }

    public function originUrl(): ?string
    {
        return null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'direction' => FinancialDirection::class,
            'status' => FinancialEntryStatus::class,
            'category_id' => 'integer',
            'issue_date' => 'date',
            'competence_date' => 'date',
            'due_date' => 'date',
            'gross_amount' => 'integer',
            'net_amount' => 'integer',
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
        return FinancialEntryFactory::new();
    }
}
