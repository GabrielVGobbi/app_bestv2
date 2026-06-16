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
            $table->string('spotify_sync_priority')->default('normal')->after('spotify_last_synced_at')->index();
            $table->unsignedSmallInteger('spotify_sync_interval_hours')->default(24)->after('spotify_sync_priority');
            $table->timestamp('next_spotify_sync_at')->nullable()->after('spotify_sync_interval_hours')->index();
            $table->unsignedInteger('spotify_sync_failures_count')->default(0)->after('next_spotify_sync_at');
            $table->timestamp('spotify_sync_paused_at')->nullable()->after('spotify_sync_failures_count')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('playlists', function (Blueprint $table): void {
            $table->dropIndex(['spotify_sync_priority']);
            $table->dropIndex(['next_spotify_sync_at']);
            $table->dropIndex(['spotify_sync_paused_at']);

            $table->dropColumn([
                'spotify_sync_priority',
                'spotify_sync_interval_hours',
                'next_spotify_sync_at',
                'spotify_sync_failures_count',
                'spotify_sync_paused_at',
            ]);
        });
    }
};
