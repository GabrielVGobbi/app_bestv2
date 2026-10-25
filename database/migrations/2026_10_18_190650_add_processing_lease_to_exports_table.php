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
        Schema::table('exports', function (Blueprint $table) {
            $table->uuid('dispatch_token')->nullable();
            $table->unsignedInteger('processing_attempt')->default(0);
            $table->string('processing_job_key', 64)->nullable();
            $table->uuid('processing_token')->nullable();
            $table->timestamp('processing_expires_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->dropIndex(['processing_expires_at']);
            $table->dropColumn(['dispatch_token', 'processing_attempt', 'processing_job_key', 'processing_token', 'processing_expires_at']);
        });
    }
};
