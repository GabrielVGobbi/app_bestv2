<?php

declare(strict_types=1);

namespace App\Modules\Services\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Modules\Services\Database\Factories\ServiceFactory;
use App\Supports\Traits\GenerateUniqueSlugTrait;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

#[Fillable([
    'uuid',
    'name',
    'slug',
    'description',
    'base_price',
    'currency',
    'is_active',
    'metadata',
])]
class Service extends Model
{
    use Exportable, GenerateUniqueSlugTrait, HasActivityLog, HasFactory, HasUuidIdentifier, Searchable, SoftDeletes;

    /** @var array<string, string> */
    protected array $exportable = [
        'name' => 'Nome',
        'slug' => 'Slug',
        'description' => 'Descricao',
        'base_price' => 'Preço Base',
        'currency' => 'Moeda',
        'is_active' => 'Ativo',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['name', 'slug', 'description'];

    /** @var array<int, string> */
    protected array $filterable = ['uuid', 'name', 'slug', 'currency', 'is_active', 'created_at', 'updated_at'];

    /** @var array<int, string> */
    protected array $sortable = ['id', 'uuid', 'name', 'slug', 'base_price', 'currency', 'is_active', 'created_at', 'updated_at'];

    protected string $activityLogName = 'services';

    protected string $defaultSortColumn = 'id';

    protected string $defaultSortDirection = 'desc';

    public function hasSaleItems(): bool
    {
        if (! Schema::hasTable('sale_items')) {
            return false;
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ServiceFactory::new();
    }
}
