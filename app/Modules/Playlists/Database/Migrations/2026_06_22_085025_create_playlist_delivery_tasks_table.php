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
        Schema::create('playlist_delivery_tasks', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('playlist_slot_id')->nullable()->constrained('playlist_slots')->nullOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('playlist_top_id')->nullable()->constrained('playlist_tops')->nullOnDelete();
            $table->foreignId('playlist_id')->nullable()->constrained('playlists')->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->index();
            $table->string('sale_order_number')->nullable()->index();
            $table->string('client_name')->nullable()->index();
            $table->string('playlist_name')->nullable()->index();
            $table->string('top_name')->nullable()->index();
            $table->string('from_track_spotify_id')->nullable()->index();
            $table->string('from_track_spotify_url')->nullable();
            $table->string('from_track_name')->nullable()->index();
            $table->json('from_track_artists')->nullable();
            $table->string('from_track_album_name')->nullable();
            $table->unsignedInteger('from_track_duration_ms')->nullable();
            $table->string('from_track_isrc')->nullable();
            $table->string('from_track_image_url')->nullable();
            $table->string('to_track_spotify_id')->nullable()->index();
            $table->string('to_track_spotify_url')->nullable();
            $table->string('to_track_name')->nullable()->index();
            $table->json('to_track_artists')->nullable();
            $table->string('to_track_album_name')->nullable();
            $table->unsignedInteger('to_track_duration_ms')->nullable();
            $table->string('to_track_isrc')->nullable();
            $table->string('to_track_image_url')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('canceled_at')->nullable()->index();
            $table->foreignId('canceled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['playlist_slot_id', 'type', 'status']);
            $table->index(['sale_id', 'status']);
            $table->index(['playlist_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playlist_delivery_tasks');
    }
};
