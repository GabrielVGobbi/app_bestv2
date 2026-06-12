<?php

declare(strict_types=1);

namespace App\Modules\Companies\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Modules\Companies\Database\Factories\CompanyFactory;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'name', 'nickname', 'cnpj', 'fiscal_metadata'])]
class Company extends Model
{
    use Exportable, HasActivityLog, HasFactory, HasUuidIdentifier, Searchable, SoftDeletes;

    /** @var array<string, string> */
    protected array $exportable = [
        'name' => 'Nome',
        'nickname' => 'Apelido',
        'cnpj' => 'CNPJ',
        'fiscal_metadata' => 'Metadados fiscais',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['name', 'nickname', 'cnpj'];

    /** @var array<int, string> */
    protected array $filterable = ['uuid', 'name', 'nickname', 'cnpj', 'created_at', 'updated_at'];

    /** @var array<int, string> */
    protected array $sortable = ['id', 'uuid', 'name', 'nickname', 'cnpj', 'created_at', 'updated_at'];

    protected string $activityLogName = 'companies';

    protected string $defaultSortColumn = 'id';

    protected string $defaultSortDirection = 'desc';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fiscal_metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return CompanyFactory::new();
    }
}
