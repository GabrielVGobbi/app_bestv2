<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Models\User;
use App\Modules\Playlists\Database\Factories\PlaylistDeliveryTaskFactory;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskStatus;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskType;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'playlist_slot_id',
    'sale_item_id',
    'sale_id',
    'playlist_top_id',
    'playlist_id',
    'type',
    'status',
    'sale_order_number',
    'client_name',
    'playlist_name',
    'top_name',
    'from_track_spotify_id',
    'from_track_spotify_url',
    'from_track_name',
    'from_track_artists',
    'from_track_album_name',
    'from_track_duration_ms',
    'from_track_isrc',
    'from_track_image_url',
    'to_track_spotify_id',
    'to_track_spotify_url',
    'to_track_name',
    'to_track_artists',
    'to_track_album_name',
    'to_track_duration_ms',
    'to_track_isrc',
    'to_track_image_url',
    'completed_at',
    'completed_by_id',
    'canceled_at',
    'canceled_by_id',
    'cancellation_reason',
    'metadata',
])]
class PlaylistDeliveryTask extends Model
{
    use Exportable, HasActivityLog, HasFactory, HasUuidIdentifier, Searchable, SoftDeletes;

    protected string $activityLogName = 'playlist_delivery_tasks';

    /** @var array<string, string> */
    protected array $exportable = [
        'type' => 'Tipo',
        'status' => 'Status',
        'sale_order_number' => 'Venda',
        'client_name' => 'Cliente',
        'playlist_name' => 'Playlist',
        'top_name' => 'TOP',
        'from_track_name' => 'Música anterior',
        'to_track_name' => 'Música nova',
        'created_at' => 'Criada em',
        'completed_at' => 'Concluída em',
    ];

    /** @var array<int, string> */
    protected array $searchable = [
        'sale_order_number',
        'client_name',
        'playlist_name',
        'top_name',
        'from_track_name',
        'from_track_spotify_url',
        'to_track_name',
        'to_track_spotify_url',
    ];

    /** @var array<int, string> */
    protected array $filterable = [
        'uuid',
        'playlist_slot_id',
        'sale_item_id',
        'sale_id',
        'playlist_top_id',
        'playlist_id',
        'type',
        'status',
        'sale_order_number',
        'client_name',
        'playlist_name',
        'top_name',
        'created_at',
        'completed_at',
        'canceled_at',
    ];

    /** @var array<int, string> */
    protected array $sortable = [
        'id',
        'uuid',
        'type',
        'status',
        'sale_order_number',
        'client_name',
        'playlist_name',
        'top_name',
        'created_at',
        'completed_at',
        'canceled_at',
        'updated_at',
    ];

    protected string $defaultSortColumn = 'id';

    protected string $defaultSortDirection = 'desc';

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
    ];

    public function playlistSlot(): BelongsTo
    {
        return $this->belongsTo(PlaylistSlot::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function playlistTop(): BelongsTo
    {
        return $this->belongsTo(PlaylistTop::class);
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PlaylistDeliveryTaskType::class,
            'status' => PlaylistDeliveryTaskStatus::class,
            'from_track_artists' => 'array',
            'to_track_artists' => 'array',
            'from_track_duration_ms' => 'integer',
            'to_track_duration_ms' => 'integer',
            'completed_at' => 'datetime',
            'canceled_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return PlaylistDeliveryTaskFactory::new();
    }
}
