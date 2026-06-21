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
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->string('spotify_playlist_check_status')->default('unchecked')->after('track_metadata')->index();
            $table->timestamp('spotify_playlist_checked_at')->nullable()->after('spotify_playlist_check_status');
            $table->unsignedInteger('spotify_playlist_track_position')->nullable()->after('spotify_playlist_checked_at');
            $table->string('spotify_playlist_match_type')->nullable()->after('spotify_playlist_track_position');
            $table->string('spotify_playlist_id_checked')->nullable()->after('spotify_playlist_match_type');
            $table->string('spotify_playlist_snapshot_id_checked')->nullable()->after('spotify_playlist_id_checked');
            $table->text('spotify_playlist_check_error')->nullable()->after('spotify_playlist_snapshot_id_checked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropColumn([
                'spotify_playlist_check_status',
                'spotify_playlist_checked_at',
                'spotify_playlist_track_position',
                'spotify_playlist_match_type',
                'spotify_playlist_id_checked',
                'spotify_playlist_snapshot_id_checked',
                'spotify_playlist_check_error',
            ]);
        });
    }
};
