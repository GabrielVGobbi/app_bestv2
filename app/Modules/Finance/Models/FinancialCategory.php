<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Database\Factories\FinancialCategoryFactory;
use App\Modules\Finance\Enums\FinancialCategoryType;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'uuid',
    'company_id',
    'parent_id',
    'code',
    'name',
    'slug',
    'description',
    'type',
    'color',
    'sort_order',
    'is_active',
])]
class FinancialCategory extends Model
{
    use Exportable, HasFactory, HasUuidIdentifier, Searchable, SoftDeletes;

    /** @var array<string, string> */
    protected array $exportable = [
        'code' => 'Codigo',
        'name' => 'Nome',
        'type' => 'Tipo',
        'full_path' => 'Caminho',
        'company_id' => 'Empresa',
        'is_active' => 'Ativa',
        'created_at' => 'Criado em',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['code', 'name', 'slug', 'description'];

    /** @var array<int, string> */
    protected array $filterable = ['uuid', 'company_id', 'parent_id', 'code', 'name', 'slug', 'type', 'is_active', 'created_at', 'updated_at'];

    /** @var array<int, string> */
    protected array $sortable = ['id', 'code', 'name', 'type', 'sort_order', 'is_active', 'created_at', 'updated_at'];

    /** @var array<int, string> */
    protected array $apiIncludes = ['parent', 'children'];

    protected string $defaultSortColumn = 'sort_order';

    protected string $defaultSortDirection = 'asc';

    /** @var array<string, mixed> */
    protected $attributes = [
        'type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function financialEntries(): HasMany
    {
        return $this->hasMany(FinancialEntry::class, 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, FinancialCategoryType|string $type): Builder
    {
        $value = $type instanceof FinancialCategoryType ? $type->value : $type;

        return $query->where('type', $value);
    }

    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        if ($companyId === null) {
            return $query->whereNull('company_id');
        }

        return $query->where(function (Builder $query) use ($companyId): void {
            $query->whereNull('company_id')
                ->orWhere('company_id', $companyId);
        });
    }

    /** @return array<int, self> */
    public function breadcrumb(): array
    {
        $breadcrumb = [];
        $category = $this;

        while ($category !== null) {
            array_unshift($breadcrumb, $category);
            $category = $category->parentCategory();
        }

        return $breadcrumb;
    }

    public function fullPath(): string
    {
        return collect($this->breadcrumb())
            ->pluck('name')
            ->implode(' > ');
    }

    public function getFullPathAttribute(): string
    {
        return $this->fullPath();
    }

    public function depth(): int
    {
        return count($this->breadcrumb()) - 1;
    }

    public function hasChildren(): bool
    {
        if (array_key_exists('children_count', $this->attributes)) {
            return (int) $this->attributes['children_count'] > 0;
        }

        if ($this->relationLoaded('children')) {
            return $this->children->isNotEmpty();
        }

        return $this->children()->exists();
    }

    /** @return array<int, int> */
    public function descendantIds(): array
    {
        /** @var Collection<int, self> $children */
        $children = $this->children()->with('childrenRecursive')->get();

        return $children
            ->flatMap(fn (self $child): array => [$child->id, ...$child->descendantIds()])
            ->values()
            ->all();
    }

    public function isDescendantOf(self $category): bool
    {
        $parent = $this->parentCategory();

        while ($parent !== null) {
            if ($parent->is($category)) {
                return true;
            }

            $parent = $parent->parentCategory();
        }

        return false;
    }

    private function parentCategory(): ?self
    {
        if ($this->parent_id === null) {
            return null;
        }

        if ($this->relationLoaded('parent')) {
            /** @var self|null $parent */
            $parent = $this->getRelation('parent');

            return $parent;
        }

        $this->loadMissing('parent');

        /** @var self|null $parent */
        $parent = $this->getRelation('parent');

        return $parent;
    }

    /** @return array<int, string> */
    public function deletionBlockers(): array
    {
        $blockers = [];

        if ($this->relatedRecordsExist('children', 'children_count')) {
            $blockers[] = 'children';
        }

        if ($this->relatedRecordsExist('financialEntries', 'financial_entries_count')) {
            $blockers[] = 'financial_entries';
        }

        return $blockers;
    }

    private function relatedRecordsExist(string $relation, string $countAttribute): bool
    {
        if (array_key_exists($countAttribute, $this->attributes)) {
            return (int) $this->attributes[$countAttribute] > 0;
        }

        if ($this->relationLoaded($relation)) {
            $related = $this->getRelation($relation);

            return $related instanceof Collection && $related->isNotEmpty();
        }

        return $this->{$relation}()->exists();
    }

    public function canBeDeleted(): bool
    {
        return $this->deletionBlockers() === [];
    }

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }

            if ($category->parent_id === null) {
                return;
            }

            if ($category->exists && $category->parent_id === $category->id) {
                throw ValidationException::withMessages([
                    'parent_id' => __('A categoria financeira nao pode ser pai dela mesma.'),
                ]);
            }

            $parent = self::query()->find($category->parent_id);

            if ($parent === null) {
                return;
            }

            if ($parent->typeValue() !== $category->typeValue()) {
                throw ValidationException::withMessages([
                    'parent_id' => __('A categoria pai deve ter o mesmo tipo da categoria financeira.'),
                ]);
            }

            if ($parent->company_id !== null && (int) $parent->company_id !== (int) $category->company_id) {
                throw ValidationException::withMessages([
                    'parent_id' => __('A categoria pai deve pertencer a mesma empresa ou ser global.'),
                ]);
            }

            if ($category->exists && $parent->isDescendantOf($category)) {
                throw ValidationException::withMessages([
                    'parent_id' => __('A categoria pai nao pode ser descendente da categoria financeira.'),
                ]);
            }
        });
    }

    private function typeValue(): string
    {
        return $this->type instanceof FinancialCategoryType ? $this->type->value : (string) $this->type;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => FinancialCategoryType::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return FinancialCategoryFactory::new();
    }
}
