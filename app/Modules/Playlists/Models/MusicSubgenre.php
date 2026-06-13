<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Models;

use App\Modules\Playlists\Database\Factories\MusicSubgenreFactory;
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
class MusicSubgenre extends Model
{
    use GenerateUniqueSlugTrait, HasFactory, HasUuidIdentifier;

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(MusicGenre::class, 'music_genre_music_subgenre')
            ->withTimestamps();
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'playlist_music_subgenre');
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
        return MusicSubgenreFactory::new();
    }
}
