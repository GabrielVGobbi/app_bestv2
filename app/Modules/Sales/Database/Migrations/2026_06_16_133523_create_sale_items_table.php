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
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->char('currency', 3)->default('BRL');
            $table->date('starts_on')->nullable()->index();
            $table->date('ends_on')->nullable()->index();
            $table->string('status')->default('draft')->index();
            $table->foreignId('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->foreignId('playlist_top_id')->nullable()->constrained('playlist_tops')->restrictOnDelete();
            $table->nullableMorphs('sellable');
            $table->string('track_spotify_id')->nullable()->index();
            $table->string('track_spotify_url')->nullable();
            $table->string('track_name')->nullable();
            $table->json('track_artists')->nullable();
            $table->string('track_album_name')->nullable();
            $table->unsignedInteger('track_duration_ms')->nullable();
            $table->string('track_isrc')->nullable();
            $table->json('track_metadata')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('sale_id');
            $table->index('service_id');
            $table->index('playlist_top_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
