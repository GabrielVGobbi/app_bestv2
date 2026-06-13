<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('playlists', function (Blueprint $table): void {
            $table->string('spotify_snapshot_id')->nullable()->after('spotify_url');
            $table->string('spotify_owner_id')->nullable()->after('spotify_snapshot_id');
            $table->string('spotify_owner_name')->nullable()->after('spotify_owner_id');
            $table->unsignedInteger('spotify_tracks_total')->nullable()->after('spotify_owner_name');
            $table->timestamp('spotify_last_synced_at')->nullable()->after('spotify_tracks_total');
            $table->unique('spotify_playlist_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('playlists', function (Blueprint $table): void {
            $table->dropUnique(['spotify_playlist_id']);
            $table->dropColumn([
                'spotify_snapshot_id',
                'spotify_owner_id',
                'spotify_owner_name',
                'spotify_tracks_total',
                'spotify_last_synced_at',
            ]);
        });
    }
};
