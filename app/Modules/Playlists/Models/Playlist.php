<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Modules\Playlists\Database\Factories\PlaylistFactory;
use App\Modules\Playlists\Enums\PlaylistStatus;
use App\Supports\Traits\GenerateUniqueSlugTrait;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'music_genre_id',
    'name',
    'slug',
    'status',
    'partner_name',
    'spotify_playlist_id',
    'spotify_url',
    'spotify_snapshot_id',
    'spotify_owner_id',
    'spotify_owner_name',
    'spotify_tracks_total',
    'spotify_last_synced_at',
    'youtube_url',
    'followers_count',
    'estimated_reach',
    'external_metadata',
    'notes',
])]
class Playlist extends Model
{
    use Exportable, GenerateUniqueSlugTrait, HasActivityLog, HasFactory, HasUuidIdentifier, SoftDeletes;
    use Searchable {
        applyApiFilterOperator as protected applyBaseApiFilterOperator;
    }

    /** @var array<string, string> */
    protected array $exportable = [
        'name' => 'Nome',
        'slug' => 'Slug',
        'status' => 'Status',
        'partner_name' => 'Parceiro',
        'spotify_playlist_id' => 'ID Spotify',
        'spotify_owner_name' => 'Dono Spotify',
        'spotify_tracks_total' => 'Faixas Spotify',
        'followers_count' => 'Seguidores',
        'estimated_reach' => 'Alcance estimado',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['name', 'slug', 'partner_name', 'spotify_playlist_id', 'spotify_owner_name', 'notes'];

    /** @var array<int, string> */
    protected array $filterable = [
        'uuid',
        'music_genre_id',
        'name',
        'slug',
        'status',
        'subgenres.id',
        'partner_name',
        'spotify_playlist_id',
        'spotify_owner_id',
        'spotify_owner_name',
        'created_at',
        'updated_at',
    ];

    /** @var array<int, string> */
    protected array $sortable = [
        'id',
        'uuid',
        'music_genre_id',
        'name',
        'slug',
        'status',
        'partner_name',
        'followers_count',
        'estimated_reach',
        'spotify_tracks_total',
        'spotify_last_synced_at',
        'created_at',
        'updated_at',
    ];

    protected string $activityLogName = 'playlists';

    protected string $defaultSortColumn = 'id';

    protected string $defaultSortDirection = 'desc';

    public function genre(): BelongsTo
    {
        return $this->belongsTo(MusicGenre::class, 'music_genre_id');
    }

    public function subgenres(): BelongsToMany
    {
        return $this->belongsToMany(MusicSubgenre::class, 'playlist_music_subgenre');
    }

    public function playlistTops(): HasMany
    {
        return $this->hasMany(PlaylistTop::class);
    }

    public function scopeWithCatalog(Builder $query): Builder
    {
        return $query->with(['genre', 'subgenres']);
    }

    protected function applyApiFilterOperator(Builder $query, string $column, string $operator, mixed $value): void
    {
        if ($column === 'subgenres.id') {
            if ($value === null || $value === '') {
                return;
            }

            $query->whereHas('subgenres', fn (Builder $subgenres): Builder => $subgenres->where('music_subgenres.id', $value));

            return;
        }

        $this->applyBaseApiFilterOperator($query, $column, $operator, $value);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PlaylistStatus::class,
            'followers_count' => 'integer',
            'estimated_reach' => 'integer',
            'spotify_tracks_total' => 'integer',
            'spotify_last_synced_at' => 'datetime',
            'external_metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return PlaylistFactory::new();
    }
}
