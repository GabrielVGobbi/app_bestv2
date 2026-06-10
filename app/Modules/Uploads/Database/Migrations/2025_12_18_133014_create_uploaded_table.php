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
        Schema::create('uploadeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->nullableMorphs('parentable');
            $table->string('name');
            $table->string('file_name');
            $table->string('original_name')->nullable();
            $table->string('mime_type');
            $table->string('path');
            $table->string('extension')->nullable();
            $table->string('disk')->default('public');
            $table->string('file_hash', 64)->nullable()->index();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('tag', 50)->nullable();
            $table->string('collection')->nullable();
            $table->boolean('is_cover')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->string('source')->default('upload');
            $table->text('uploaded_images')->nullable();

            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['parentable_type', 'parentable_id', 'tag'], 'uploadeds_parent_tag_idx');
            $table->index(['parentable_type', 'parentable_id', 'file_hash'], 'uploadeds_parent_hash_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploadeds');
    }
};
