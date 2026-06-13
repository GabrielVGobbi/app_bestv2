<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Modules\Playlists\Database\Factories\TopFactory;
use App\Supports\Traits\GenerateUniqueSlugTrait;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'name',
    'slug',
    'default_slots_quantity',
    'description',
    'sort_order',
    'is_active',
])]
class Top extends Model
{
    use Exportable, GenerateUniqueSlugTrait, HasActivityLog, HasFactory, HasUuidIdentifier, Searchable, SoftDeletes;

    /** @var array<string, string> */
    protected array $exportable = [
        'name' => 'Nome',
        'slug' => 'Slug',
        'default_slots_quantity' => 'Vagas padrão',
        'description' => 'Descrição',
        'sort_order' => 'Ordem',
        'is_active' => 'Ativo',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['name', 'slug', 'description'];

    /** @var array<int, string> */
    protected array $filterable = ['uuid', 'name', 'slug', 'is_active', 'created_at', 'updated_at'];

    /** @var array<int, string> */
    protected array $sortable = ['id', 'uuid', 'name', 'slug', 'default_slots_quantity', 'sort_order', 'is_active', 'created_at', 'updated_at'];

    protected string $activityLogName = 'playlist_tops_catalog';

    protected string $defaultSortColumn = 'sort_order';

    protected string $defaultSortDirection = 'asc';

    public function playlistTops(): HasMany
    {
        return $this->hasMany(PlaylistTop::class);
    }

    public function hasCommercialHistory(): bool
    {
        return $this->playlistTops()->withTrashed()->exists();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'default_slots_quantity' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return TopFactory::new();
    }
}
