<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Database\Factories\PaymentMethodFactory;
use App\Modules\Finance\Enums\PaymentMethodType;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'company_id',
    'code',
    'name',
    'type',
    'requires_reference',
    'is_active',
    'sort_order',
    'metadata',
])]
class PaymentMethod extends Model
{
    use Exportable, HasFactory, HasUuidIdentifier, Searchable;

    /** @var array<string, string> */
    protected array $exportable = [
        'code' => 'Codigo',
        'name' => 'Nome',
        'type' => 'Tipo',
        'requires_reference' => 'Exige referencia',
        'is_active' => 'Ativo',
        'sort_order' => 'Ordenacao',
        'created_at' => 'Criado em',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['code', 'name', 'type'];

    /** @var array<int, string> */
    protected array $filterable = ['company_id', 'code', 'name', 'type', 'requires_reference', 'is_active', 'created_at', 'updated_at'];

    /** @var array<int, string> */
    protected array $sortable = ['id', 'code', 'name', 'type', 'requires_reference', 'is_active', 'sort_order', 'created_at', 'updated_at'];

    protected string $defaultSortColumn = 'sort_order';

    protected string $defaultSortDirection = 'asc';

    /** @var array<string, mixed> */
    protected $attributes = [
        'type' => 'other',
        'requires_reference' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        if ($companyId === null) {
            return $query->whereNull('company_id');
        }

        return $query->where(function (Builder $scope) use ($companyId): void {
            $scope
                ->whereNull('company_id')
                ->orWhere('company_id', $companyId);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'requires_reference' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return PaymentMethodFactory::new();
    }
}
