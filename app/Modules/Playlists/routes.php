<?php

use App\Modules\Playlists\Http\Controllers\Api\PlaylistApiController;
use App\Modules\Playlists\Http\Controllers\MusicCatalogController;
use App\Modules\Playlists\Http\Controllers\MusicGenreController;
use App\Modules\Playlists\Http\Controllers\MusicSubgenreController;
use App\Modules\Playlists\Http\Controllers\PlaylistController;
use App\Modules\Playlists\Http\Controllers\SpotifyPlaylistPreviewController;
use App\Modules\Playlists\Http\Controllers\SpotifyPlaylistSyncController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('admin/music-catalog', MusicCatalogController::class)->name('playlists.music-catalog.index');
    Route::post('playlists/music-genres', [MusicGenreController::class, 'store'])->name('playlists.music-genres.store');
    Route::patch('playlists/music-genres/{musicGenre}', [MusicGenreController::class, 'update'])->name('playlists.music-genres.update');
    Route::patch('playlists/music-genres/{musicGenre}/status', [MusicGenreController::class, 'updateStatus'])->name('playlists.music-genres.status');
    Route::post('playlists/music-genres/{musicGenre}/subgenres', [MusicGenreController::class, 'storeSubgenre'])->name('playlists.music-genres.subgenres.store');
    Route::delete('playlists/music-genres/{musicGenre}/subgenres/{musicSubgenre}', [MusicGenreController::class, 'destroySubgenre'])->name('playlists.music-genres.subgenres.destroy');
    Route::patch('playlists/music-subgenres/{musicSubgenre}', [MusicSubgenreController::class, 'update'])->name('playlists.music-subgenres.update');
    Route::patch('playlists/music-subgenres/{musicSubgenre}/status', [MusicSubgenreController::class, 'updateStatus'])->name('playlists.music-subgenres.status');
    Route::post('playlists/spotify/preview', SpotifyPlaylistPreviewController::class)->name('playlists.spotify.preview');
    Route::patch('playlists/{playlist}/spotify/sync', SpotifyPlaylistSyncController::class)->name('playlists.spotify.sync');
    Route::patch('playlists/{playlist}/restore', [PlaylistController::class, 'restore'])->name('playlists.restore');
    Route::patch('playlists/{playlist}/status', [PlaylistController::class, 'updateStatus'])->name('playlists.status');
    Route::resource('playlists', PlaylistController::class)->parameters(['playlists' => 'playlist']);
});

Route::prefix('api/v1')
    ->as('api.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function (): void {
        Route::patch('playlists/{playlist}/restore', [PlaylistApiController::class, 'restore'])->name('playlists.restore');
        Route::apiResource('playlists', PlaylistApiController::class)
            ->parameters(['playlists' => 'playlist']);
    });
