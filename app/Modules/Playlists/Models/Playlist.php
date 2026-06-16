<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Modules\Playlists\Database\Factories\PlaylistFactory;
use App\Modules\Playlists\Enums\PlaylistStatus;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Traits\HasUploads;
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
use Illuminate\Database\Eloquent\Relations\HasOne;
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
    'spotify_name',
    'spotify_description',
    'spotify_public',
    'spotify_image_url',
    'spotify_cover_upload_id',
    'spotify_snapshot_id',
    'spotify_owner_id',
    'spotify_owner_name',
    'spotify_tracks_total',
    'spotify_last_synced_at',
    'spotify_sync_priority',
    'spotify_sync_interval_hours',
    'next_spotify_sync_at',
    'spotify_sync_failures_count',
    'spotify_sync_paused_at',
    'youtube_url',
    'followers_count',
    'estimated_reach',
    'external_metadata',
    'notes',
])]
class Playlist extends Model
{
    use Exportable, GenerateUniqueSlugTrait, HasActivityLog, HasFactory, HasUploads, HasUuidIdentifier, SoftDeletes;
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
        'spotify_name' => 'Nome Spotify',
        'spotify_owner_name' => 'Dono Spotify',
        'spotify_tracks_total' => 'Faixas Spotify',
        'followers_count' => 'Seguidores',
        'estimated_reach' => 'Alcance estimado',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['name', 'slug', 'partner_name', 'spotify_playlist_id', 'spotify_name', 'spotify_owner_name', 'notes'];

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
        'spotify_name',
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
        'spotify_sync_priority',
        'next_spotify_sync_at',
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

    public function spotifySnapshots(): HasMany
    {
        return $this->hasMany(PlaylistSpotifySnapshot::class);
    }

    public function changeEvents(): HasMany
    {
        return $this->hasMany(PlaylistChangeEvent::class);
    }

    public function spotifySyncRuns(): HasMany
    {
        return $this->hasMany(PlaylistSpotifySyncRun::class);
    }

    public function latestSpotifySnapshot(): HasOne
    {
        return $this->hasOne(PlaylistSpotifySnapshot::class)->latestOfMany('captured_at');
    }

    public function latestSpotifySyncRun(): HasOne
    {
        return $this->hasOne(PlaylistSpotifySyncRun::class)->latestOfMany('started_at');
    }

    public function spotifyCoverUpload(): BelongsTo
    {
        return $this->belongsTo(Uploaded::class, 'spotify_cover_upload_id');
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
            'spotify_public' => 'boolean',
            'spotify_tracks_total' => 'integer',
            'spotify_last_synced_at' => 'datetime',
            'spotify_sync_interval_hours' => 'integer',
            'next_spotify_sync_at' => 'datetime',
            'spotify_sync_failures_count' => 'integer',
            'spotify_sync_paused_at' => 'datetime',
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
