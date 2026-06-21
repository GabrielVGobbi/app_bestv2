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
        Schema::create('playlist_slots', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('playlist_top_id')->constrained('playlist_tops')->restrictOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->cascadeOnDelete();
            $table->string('status')->index();
            $table->date('starts_on')->nullable()->index();
            $table->date('ends_on')->nullable()->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('reserved_until')->nullable()->index();
            $table->string('track_spotify_id')->nullable()->index();
            $table->string('track_spotify_url');
            $table->string('track_name');
            $table->json('track_artists')->nullable();
            $table->string('track_album_name')->nullable();
            $table->unsignedInteger('track_duration_ms')->nullable();
            $table->string('track_isrc')->nullable();
            $table->json('track_metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('canceled_at')->nullable()->index();
            $table->foreignId('canceled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('playlist_top_id');
            $table->index('sale_item_id');
            $table->index(['starts_on', 'ends_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playlist_slots');
    }
};
