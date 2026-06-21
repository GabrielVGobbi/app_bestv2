<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Models;

use App\Models\User;
use App\Modules\Playlists\Database\Factories\PlaylistSlotFactory;
use App\Modules\Playlists\Enums\PlaylistSlotStatus;
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
    'playlist_top_id',
    'sale_item_id',
    'status',
    'starts_on',
    'ends_on',
    'quantity',
    'reserved_until',
    'track_spotify_id',
    'track_spotify_url',
    'track_name',
    'track_artists',
    'track_album_name',
    'track_duration_ms',
    'track_isrc',
    'track_metadata',
    'notes',
    'created_by_id',
    'canceled_at',
    'canceled_by_id',
    'cancellation_reason',
])]
class PlaylistSlot extends Model
{
    use HasActivityLog, HasFactory, HasUuidIdentifier, SoftDeletes;

    protected string $activityLogName = 'playlist_slots';

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending_schedule',
        'quantity' => 1,
    ];

    public function playlistTop(): BelongsTo
    {
        return $this->belongsTo(PlaylistTop::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function canceler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by_id');
    }

    public function hasCompletePeriod(): bool
    {
        return $this->starts_on !== null && $this->ends_on !== null;
    }

    public function occupiesCapacity(): bool
    {
        return $this->status->occupiesCapacity() && $this->hasCompletePeriod();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PlaylistSlotStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'quantity' => 'integer',
            'reserved_until' => 'datetime',
            'track_artists' => 'array',
            'track_metadata' => 'array',
            'canceled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return PlaylistSlotFactory::new();
    }
}
