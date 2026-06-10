<?php

declare(strict_types=1);

namespace App\Modules\Clients\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
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

#[Fillable(['uuid', 'name', 'email'])]
class Client extends Model implements TimelineSubject
{
    use Exportable, HasActivityLog, HasComments, HasFactory, HasTimeline, HasUuidIdentifier, Searchable;

    /** @var array<string, string> */
    protected array $exportable = [
        'name' => 'Name',
        'email' => 'Email',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['name', 'email'];

    /** @var array<int, string> */
    protected array $filterable = ['uuid', 'name', 'email', 'created_at', 'updated_at'];

    /** @var array<int, string> */
    protected array $sortable = ['id', 'uuid', 'name', 'email', 'created_at', 'updated_at'];

    protected string $activityLogName = 'clients';

    /**
     * @var array{
     *     fields: array<int, string>,
     *     labels: array<string, string>,
     *     hidden: array<int, string>
     * }
     */
    protected array $timelineActivity = [
        'fields' => ['name', 'email'],
        'labels' => [
            'name' => 'Nome',
            'email' => 'Email',
        ],
        'hidden' => [],
    ];

    protected string $defaultSortColumn = 'id';

    protected string $defaultSortDirection = 'desc';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ClientFactory::new();
    }
}
