<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_genres', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('music_subgenres', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('music_genre_id')->constrained('music_genres')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['music_genre_id', 'slug']);
        });

        Schema::create('playlists', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('music_genre_id')->constrained('music_genres')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active')->index();
            $table->string('partner_name')->nullable()->index();
            $table->string('spotify_playlist_id')->nullable()->index();
            $table->string('spotify_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->unsignedBigInteger('followers_count')->nullable();
            $table->unsignedBigInteger('estimated_reach')->nullable();
            $table->json('external_metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('playlist_music_subgenre', function (Blueprint $table): void {
            $table->foreignId('playlist_id')->constrained('playlists')->cascadeOnDelete();
            $table->foreignId('music_subgenre_id')->constrained('music_subgenres')->cascadeOnDelete();
            $table->primary(['playlist_id', 'music_subgenre_id'], 'playlist_subgenre_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_music_subgenre');
        Schema::dropIfExists('playlists');
        Schema::dropIfExists('music_subgenres');
        Schema::dropIfExists('music_genres');
    }
};
