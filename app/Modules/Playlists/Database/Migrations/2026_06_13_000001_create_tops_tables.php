<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tops', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('default_slots_quantity');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'deleted_at']);
        });

        Schema::create('playlist_tops', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('playlist_id')->constrained('playlists')->restrictOnDelete();
            $table->foreignId('top_id')->constrained('tops')->restrictOnDelete();
            $table->unsignedInteger('slots_quantity');
            $table->unsignedInteger('occupied_slots_count')->default(0);
            $table->unsignedBigInteger('price')->default(0);
            $table->char('currency', 3)->default('BRL');
            $table->json('metrics')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('playlist_id');
            $table->index('top_id');
            $table->unique(['playlist_id', 'top_id', 'deleted_at'], 'playlist_tops_unique_active_top');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_tops');
        Schema::dropIfExists('tops');
    }
};
