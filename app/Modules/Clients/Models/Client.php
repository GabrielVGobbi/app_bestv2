<?php

declare(strict_types=1);

namespace App\Modules\Clients\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Models\User;
use App\Modules\Addresses\Traits\HasAddresses;
use App\Modules\Clients\Database\Factories\ClientFactory;
use App\Modules\Timeline\Contracts\TimelineSubject;
use App\Modules\Timeline\Traits\HasComments;
use App\Modules\Timeline\Traits\HasTimeline;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'company_id',
    'responsible_user_id',
    'name',
    'company_name',
    'email',
    'phone',
    'document',
    'musical_genre',
    'is_agent',
    'notes',
    'classification',
    'source_id',
    'status',
])]
class Client extends Model implements TimelineSubject
{
    use Exportable, HasActivityLog, HasAddresses, HasComments, HasFactory, HasTimeline, HasUuidIdentifier, Searchable;

    public const array CLASSIFICATIONS = [
        'Classe S',
        'Classe A',
        'Classe B',
        'Classe C',
        'Classe Iniciante',
    ];

    /** @var array<string, string> */
    protected array $exportable = [
        'name' => 'Nome',
        'email' => 'Email',
        'phone' => 'Telefone',
        'document' => 'Documento',
        'status' => 'Status',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['name', 'company_name', 'email', 'phone', 'document', 'musical_genre', 'classification'];

    /** @var array<int, string> */
    protected array $filterable = [
        'uuid',
        'company_id',
        'responsible_user_id',
        'name',
        'company_name',
        'email',
        'phone',
        'document',
        'musical_genre',
        'is_agent',
        'classification',
        'source_id',
        'status',
        'created_at',
        'updated_at',
    ];

    /** @var array<int, string> */
    protected array $sortable = [
        'id',
        'uuid',
        'company_id',
        'responsible_user_id',
        'name',
        'company_name',
        'email',
        'phone',
        'document',
        'musical_genre',
        'is_agent',
        'classification',
        'source_id',
        'status',
        'created_at',
        'updated_at',
    ];

    protected string $activityLogName = 'clients';

    /**
     * @var array{
     *     fields: array<int, string>,
     *     labels: array<string, string>,
     *     hidden: array<int, string>
     * }
     */
    protected array $timelineActivity = [
        'fields' => [
            'company_id',
            'responsible_user_id',
            'name',
            'company_name',
            'email',
            'phone',
            'document',
            'musical_genre',
            'is_agent',
            'notes',
            'classification',
            'source_id',
            'status',
        ],
        'labels' => [
            'company_id' => 'Empresa',
            'responsible_user_id' => 'Responsável',
            'name' => 'Nome',
            'company_name' => 'Nome da empresa',
            'email' => 'Email',
            'phone' => 'Telefone',
            'document' => 'Documento',
            'musical_genre' => 'Gênero musical',
            'is_agent' => 'Agente',
            'notes' => 'Observações',
            'classification' => 'Classificação',
            'source_id' => 'Origem',
            'status' => 'Status',
        ],
        'hidden' => [],
    ];

    protected string $defaultSortColumn = 'id';

    protected string $defaultSortDirection = 'desc';

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_agent' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ClientFactory::new();
    }
}
