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
        Schema::create('playlist_spotify_snapshots', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('playlist_id')->constrained('playlists')->cascadeOnDelete();
            $table->foreignId('spotify_cover_upload_id')->nullable()->constrained('uploadeds')->nullOnDelete();
            $table->unsignedBigInteger('followers_count')->nullable();
            $table->bigInteger('followers_delta')->nullable();
            $table->decimal('followers_delta_percent', 10, 4)->nullable();
            $table->string('spotify_name')->nullable();
            $table->text('spotify_description')->nullable();
            $table->string('spotify_owner_id')->nullable();
            $table->string('spotify_owner_name')->nullable();
            $table->unsignedInteger('spotify_tracks_total')->nullable();
            $table->string('spotify_snapshot_id')->nullable();
            $table->boolean('spotify_public')->nullable();
            $table->string('spotify_image_url')->nullable();
            $table->json('spotify_images')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('captured_at')->index();
            $table->timestamps();

            $table->index(['playlist_id', 'captured_at']);
            $table->index('followers_delta');
        });

        Schema::create('playlist_change_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('playlist_id')->constrained('playlists')->cascadeOnDelete();
            $table->foreignId('playlist_spotify_snapshot_id')->nullable()->constrained('playlist_spotify_snapshots')->nullOnDelete();
            $table->string('field')->index();
            $table->string('label');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('source')->default('spotify')->index();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['playlist_id', 'occurred_at']);
        });

        Schema::create('playlist_spotify_sync_runs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('playlist_id')->constrained('playlists')->cascadeOnDelete();
            $table->foreignId('playlist_spotify_snapshot_id')->nullable()->constrained('playlist_spotify_snapshots')->nullOnDelete();
            $table->string('trigger')->index();
            $table->string('status')->index();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('http_status')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['playlist_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playlist_spotify_sync_runs');
        Schema::dropIfExists('playlist_change_events');
        Schema::dropIfExists('playlist_spotify_snapshots');
    }
};
