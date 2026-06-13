<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Providers;

use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Policies\PlaylistPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PlaylistsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Gate::policy(Playlist::class, PlaylistPolicy::class);
    }
}
