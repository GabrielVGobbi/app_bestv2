<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Sales\Database\Factories\SaleItemFactory;
use App\Modules\Sales\Enums\SaleItemStatus;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Enums\SpotifyPlaylistPresenceStatus;
use App\Modules\Services\Models\Service;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'sale_id',
    'type',
    'description',
    'quantity',
    'unit_amount',
    'discount_amount',
    'total_amount',
    'currency',
    'starts_on',
    'ends_on',
    'status',
    'service_id',
    'playlist_top_id',
    'sellable_type',
    'sellable_id',
    'track_spotify_id',
    'track_spotify_url',
    'track_name',
    'track_artists',
    'track_album_name',
    'track_duration_ms',
    'track_isrc',
    'track_metadata',
    'spotify_playlist_check_status',
    'spotify_playlist_checked_at',
    'spotify_playlist_track_position',
    'spotify_playlist_match_type',
    'spotify_playlist_id_checked',
    'spotify_playlist_snapshot_id_checked',
    'spotify_playlist_check_error',
    'metadata',
    'created_by_id',
])]
class SaleItem extends Model
{
    use HasActivityLog, HasFactory, HasUuidIdentifier, SoftDeletes;

    protected string $activityLogName = 'sale_items';

    /** @var array<string, mixed> */
    protected $attributes = [
        'type' => 'service',
        'quantity' => 1,
        'unit_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
        'currency' => 'BRL',
        'status' => 'draft',
        'spotify_playlist_check_status' => 'unchecked',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function playlistTop(): BelongsTo
    {
        return $this->belongsTo(PlaylistTop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function sellable(): MorphTo
    {
        return $this->morphTo();
    }

    public function hasCompletePeriod(): bool
    {
        return $this->starts_on !== null && $this->ends_on !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => SaleItemType::class,
            'quantity' => 'integer',
            'unit_amount' => 'integer',
            'discount_amount' => 'integer',
            'total_amount' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => SaleItemStatus::class,
            'track_artists' => 'array',
            'track_metadata' => 'array',
            'spotify_playlist_check_status' => SpotifyPlaylistPresenceStatus::class,
            'spotify_playlist_checked_at' => 'datetime',
            'spotify_playlist_track_position' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return SaleItemFactory::new();
    }
}
