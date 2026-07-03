<?php

use App\Modules\Playlists\Http\Controllers\Api\PlaylistApiController;
use App\Modules\Playlists\Http\Controllers\Api\PlaylistDeliveryTaskApiController;
use App\Modules\Playlists\Http\Controllers\Api\TopApiController;
use App\Modules\Playlists\Http\Controllers\MusicCatalogController;
use App\Modules\Playlists\Http\Controllers\MusicGenreController;
use App\Modules\Playlists\Http\Controllers\MusicSubgenreController;
use App\Modules\Playlists\Http\Controllers\PlaylistController;
use App\Modules\Playlists\Http\Controllers\PlaylistDeliveryTaskController;
use App\Modules\Playlists\Http\Controllers\PlaylistMetricsController;
use App\Modules\Playlists\Http\Controllers\PlaylistSlotController;
use App\Modules\Playlists\Http\Controllers\PlaylistSlotTrackController;
use App\Modules\Playlists\Http\Controllers\PlaylistTopController;
use App\Modules\Playlists\Http\Controllers\SpotifyPlaylistPreviewController;
use App\Modules\Playlists\Http\Controllers\SpotifyPlaylistSyncController;
use App\Modules\Playlists\Http\Controllers\TopController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('admin/music-catalog', MusicCatalogController::class)->name('playlists.music-catalog.index');
    Route::patch('playlist-tops/{top}/restore', [TopController::class, 'restore'])->name('playlist-tops.restore');
    Route::resource('playlist-tops', TopController::class)->parameters(['playlist-tops' => 'top']);
    Route::post('playlists/music-genres', [MusicGenreController::class, 'store'])->name('playlists.music-genres.store');
    Route::patch('playlists/music-genres/{musicGenre}', [MusicGenreController::class, 'update'])->name('playlists.music-genres.update');
    Route::patch('playlists/music-genres/{musicGenre}/status', [MusicGenreController::class, 'updateStatus'])->name('playlists.music-genres.status');
    Route::post('playlists/music-genres/{musicGenre}/subgenres', [MusicGenreController::class, 'storeSubgenre'])->name('playlists.music-genres.subgenres.store');
    Route::delete('playlists/music-genres/{musicGenre}/subgenres/{musicSubgenre}', [MusicGenreController::class, 'destroySubgenre'])->name('playlists.music-genres.subgenres.destroy');
    Route::patch('playlists/music-subgenres/{musicSubgenre}', [MusicSubgenreController::class, 'update'])->name('playlists.music-subgenres.update');
    Route::patch('playlists/music-subgenres/{musicSubgenre}/status', [MusicSubgenreController::class, 'updateStatus'])->name('playlists.music-subgenres.status');
    Route::get('playlists/metrics', PlaylistMetricsController::class)->name('playlists.metrics.index');
    Route::post('playlists/spotify/preview', SpotifyPlaylistPreviewController::class)->name('playlists.spotify.preview');
    Route::get('playlists/delivery-tasks', [PlaylistDeliveryTaskController::class, 'index'])->name('playlists.delivery-tasks.index');
    Route::patch('playlist-delivery-tasks/{playlistDeliveryTask}/complete', [PlaylistDeliveryTaskController::class, 'complete'])->name('playlist-delivery-tasks.complete');
    Route::get('playlist-tops/{playlistTop}/slots/availability', [PlaylistSlotController::class, 'availability'])->name('playlist-slots.availability');
    Route::patch('playlist-slots/{playlistSlot}/schedule', [PlaylistSlotController::class, 'schedule'])->name('playlist-slots.schedule');
    Route::patch('playlist-slots/{playlistSlot}/cancel', [PlaylistSlotController::class, 'cancel'])->name('playlist-slots.cancel');
    Route::patch('playlist-slots/{playlistSlot}/track', [PlaylistSlotTrackController::class, 'update'])->name('playlist-slots.track.update');
    Route::patch('playlists/{playlist}/spotify/sync', SpotifyPlaylistSyncController::class)->name('playlists.spotify.sync');
    Route::post('playlists/{playlist}/tops', [PlaylistTopController::class, 'store'])->name('playlists.tops.store');
    Route::patch('playlists/{playlist}/tops/{playlistTop}', [PlaylistTopController::class, 'update'])->name('playlists.tops.update');
    Route::delete('playlists/{playlist}/tops/{playlistTop}', [PlaylistTopController::class, 'destroy'])->name('playlists.tops.destroy');
    Route::patch('playlists/{playlist}/restore', [PlaylistController::class, 'restore'])->name('playlists.restore');
    Route::patch('playlists/{playlist}/status', [PlaylistController::class, 'updateStatus'])->name('playlists.status');
    Route::resource('playlists', PlaylistController::class)->parameters(['playlists' => 'playlist']);
});

Route::prefix('api/v1')
    ->as('api.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function (): void {
        Route::get('playlist-delivery-tasks', [PlaylistDeliveryTaskApiController::class, 'index'])->name('playlist-delivery-tasks.index');
        Route::patch('playlist-tops/{top}/restore', [TopApiController::class, 'restore'])->name('playlist-tops.restore');
        Route::apiResource('playlist-tops', TopApiController::class)
            ->parameters(['playlist-tops' => 'top']);
        Route::patch('playlists/{playlist}/restore', [PlaylistApiController::class, 'restore'])->name('playlists.restore');
        Route::apiResource('playlists', PlaylistApiController::class)
            ->parameters(['playlists' => 'playlist']);
    });
