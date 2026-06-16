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
            $table->string('spotify_name')->nullable()->after('spotify_url');
            $table->text('spotify_description')->nullable()->after('spotify_name');
            $table->boolean('spotify_public')->nullable()->after('spotify_description');
            $table->string('spotify_image_url')->nullable()->after('spotify_public');
            $table->foreignId('spotify_cover_upload_id')
                ->nullable()
                ->after('spotify_image_url')
                ->constrained('uploadeds')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('playlists', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('spotify_cover_upload_id');
            $table->dropColumn([
                'spotify_name',
                'spotify_description',
                'spotify_public',
                'spotify_image_url',
            ]);
        });
    }
};
