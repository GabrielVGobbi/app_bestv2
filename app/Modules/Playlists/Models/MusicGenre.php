<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Models;

use App\Modules\Playlists\Database\Factories\MusicGenreFactory;
use App\Supports\Traits\GenerateUniqueSlugTrait;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'uuid',
    'name',
    'slug',
    'is_active',
])]
class MusicGenre extends Model
{
    use GenerateUniqueSlugTrait, HasFactory, HasUuidIdentifier;

    public function subgenres(): BelongsToMany
    {
        return $this->belongsToMany(MusicSubgenre::class, 'music_genre_music_subgenre')
            ->withTimestamps();
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return MusicGenreFactory::new();
    }
}
