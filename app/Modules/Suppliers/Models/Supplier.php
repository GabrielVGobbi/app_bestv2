<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Suppliers\Database\Factories\SupplierFactory;
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
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'company_id',
    'name',
    'company_name',
    'document',
    'email',
    'phone',
    'notes',
    'status',
    'metadata',
])]
class Supplier extends Model implements TimelineSubject
{
    use Exportable, HasActivityLog, HasComments, HasFactory, HasTimeline, HasUuidIdentifier, Searchable, SoftDeletes;

    protected string $activityLogName = 'suppliers';

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['name', 'company_name', 'document', 'email', 'phone'];

    /** @var array<int, string> */
    protected array $filterable = [
        'uuid',
        'company_id',
        'name',
        'company_name',
        'document',
        'email',
        'phone',
        'status',
        'created_at',
        'updated_at',
    ];

    /** @var array<int, string> */
    protected array $sortable = [
        'id',
        'uuid',
        'company_id',
        'name',
        'company_name',
        'document',
        'email',
        'phone',
        'status',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected array $exportable = [
        'name' => 'Nome',
        'company_name' => 'Razao social',
        'document' => 'Documento',
        'email' => 'Email',
        'phone' => 'Telefone',
        'status' => 'Status',
    ];

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
            'name',
            'company_name',
            'document',
            'email',
            'phone',
            'notes',
            'status',
        ],
        'labels' => [
            'company_id' => 'Empresa',
            'name' => 'Nome',
            'company_name' => 'Razao social',
            'document' => 'Documento',
            'email' => 'Email',
            'phone' => 'Telefone',
            'notes' => 'Observacoes',
            'status' => 'Status',
        ],
        'hidden' => [],
    ];

    protected string $defaultSortColumn = 'id';

    protected string $defaultSortDirection = 'desc';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function financialEntries(): MorphMany
    {
        return $this->morphMany(FinancialEntry::class, 'counterparty');
    }

    /** @return array<int, string> */
    public function deletionBlockers(): array
    {
        $blockers = [];

        if ($this->financialEntries()->exists()) {
            $blockers[] = 'financial_entries';
        }

        return $blockers;
    }

    public function canBeDeleted(): bool
    {
        return $this->deletionBlockers() === [];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return SupplierFactory::new();
    }
}
