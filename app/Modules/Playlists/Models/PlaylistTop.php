<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Models;

use App\Modules\Playlists\Database\Factories\PlaylistTopFactory;
use App\Modules\Sales\Models\SaleItem;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

#[Fillable([
    'uuid',
    'playlist_id',
    'top_id',
    'slots_quantity',
    'occupied_slots_count',
    'price',
    'currency',
    'metrics',
    'notes',
    'is_active',
])]
class PlaylistTop extends Model
{
    use HasActivityLog, HasFactory, HasUuidIdentifier, SoftDeletes;

    protected string $activityLogName = 'playlist_tops';

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function top(): BelongsTo
    {
        return $this->belongsTo(Top::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function playlistSlots(): HasMany
    {
        return $this->hasMany(PlaylistSlot::class);
    }

    public function availableSlotsCount(): int
    {
        return max(0, $this->slots_quantity - $this->occupied_slots_count);
    }

    public function hasCommercialHistory(): bool
    {
        if (! Schema::hasTable('playlist_slots')) {
            return false;
        }

        return $this->playlistSlots()->exists() || $this->saleItems()->exists();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'slots_quantity' => 'integer',
            'occupied_slots_count' => 'integer',
            'price' => 'integer',
            'metrics' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return PlaylistTopFactory::new();
    }
}
