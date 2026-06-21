<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Sales\Database\Factories\SaleFactory;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Timeline\Contracts\TimelineSubject;
use App\Modules\Timeline\Traits\HasComments;
use App\Modules\Timeline\Traits\HasTimeline;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasStatusTransitions;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'company_id',
    'client_id',
    'seller_id',
    'created_by_id',
    'status',
    'order_number',
    'title',
    'notes',
    'subtotal_amount',
    'discount_amount',
    'total_amount',
    'currency',
    'confirmed_at',
    'confirmed_by_id',
    'finished_at',
    'finished_by_id',
    'canceled_at',
    'canceled_by_id',
    'cancellation_reason',
])]
class Sale extends Model implements TimelineSubject
{
    use Exportable,
        HasActivityLog,
        HasComments,
        HasFactory,
        HasStatusTransitions,
        HasTimeline,
        HasUuidIdentifier,
        Searchable,
        SoftDeletes;

    protected string $activityLogName = 'sales';

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
            'client_id',
            'seller_id',
            'status',
            'order_number',
            'title',
            'notes',
            'subtotal_amount',
            'discount_amount',
            'total_amount',
            'confirmed_at',
            'finished_at',
            'canceled_at',
            'cancellation_reason',
            'item_service',
            'item_playlist_top',
        ],
        'labels' => [
            'company_id' => 'Empresa',
            'client_id' => 'Cliente',
            'seller_id' => 'Vendedor',
            'status' => 'Status',
            'order_number' => 'Número',
            'title' => 'Título',
            'notes' => 'Observações',
            'subtotal_amount' => 'Subtotal',
            'discount_amount' => 'Desconto',
            'total_amount' => 'Total',
            'confirmed_at' => 'Confirmada em',
            'finished_at' => 'Finalizada em',
            'canceled_at' => 'Cancelada em',
            'cancellation_reason' => 'Motivo do cancelamento',
            'item_service' => 'Serviço do item',
            'item_playlist_top' => 'TOP da playlist',
        ],
        'hidden' => [],
    ];

    /** @var array<string, string> */
    protected array $exportable = [
        'order_number' => 'Número',
        'title' => 'Título',
        'status' => 'Status',
        'subtotal_amount' => 'Subtotal',
        'discount_amount' => 'Desconto',
        'total_amount' => 'Total',
        'currency' => 'Moeda',
        'confirmed_at' => 'Confirmada em',
        'finished_at' => 'Finalizada em',
        'canceled_at' => 'Cancelada em',
        'created_at' => 'Criada em',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['order_number', 'title', 'notes'];

    /** @var array<int, string> */
    protected array $filterable = [
        'uuid',
        'company_id',
        'client_id',
        'seller_id',
        'status',
        'order_number',
        'title',
        'subtotal_amount',
        'discount_amount',
        'total_amount',
        'confirmed_at',
        'finished_at',
        'canceled_at',
        'created_at',
        'updated_at',
    ];

    /** @var array<int, string> */
    protected array $sortable = [
        'id',
        'uuid',
        'company_id',
        'client_id',
        'seller_id',
        'status',
        'order_number',
        'title',
        'subtotal_amount',
        'discount_amount',
        'total_amount',
        'confirmed_at',
        'finished_at',
        'canceled_at',
        'created_at',
        'updated_at',
    ];

    protected string $defaultSortColumn = 'id';

    protected string $defaultSortDirection = 'desc';

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'subtotal_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
        'currency' => 'BRL',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }

    public function finisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finished_by_id');
    }

    public function canceler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function statusEnumClass(): string
    {
        return SaleStatus::class;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [SaleStatus::Draft, SaleStatus::Quoted], true);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($this->userHasPermission($user, 'sales.view')) {
            return $query;
        }

        if ($this->userHasPermission($user, 'sales.view-own')) {
            return $query->where('seller_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'total_amount' => 'integer',
            'confirmed_at' => 'datetime',
            'finished_at' => 'datetime',
            'canceled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return SaleFactory::new();
    }

    private function userHasPermission(User $user, string $slug): bool
    {
        $superRole = config('permissions.super_role', 'developer');

        if ($user->roles()->where('slug', $superRole)->exists()) {
            return true;
        }

        if ($user->permissions()->where('slug', $slug)->exists()) {
            return true;
        }

        return Permission::query()
            ->where('slug', $slug)
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('roles.id', $user->roles()->select('roles.id')))
            ->exists();
    }
}
