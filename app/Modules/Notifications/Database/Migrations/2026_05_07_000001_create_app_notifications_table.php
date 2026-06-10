<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type')->index();
            $table->string('category')->index();
            $table->string('title');
            $table->text('body');

            /**
             * Only display information and auxiliary IDs (e.g., export_uuid, file_name).
             * Never carry sensitive business rules, permissions, prices, or logic.
             */
            $table->json('data')->nullable();

            /**
             * Array of action buttons: { label, type, variant, requires_confirmation, confirmation_message }
             */
            $table->json('actions')->nullable();

            /**
             * Tracks the lifecycle of actionable notifications.
             * Values: null (no action yet), pending, executed, rejected, expired, cancelled
             */
            $table->string('action_status')->nullable()->index();

            $table->string('link')->nullable();

            $table->nullableMorphs('notifiable');

            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            // Composite indexes for common query patterns
            $table->index(['recipient_id', 'read_at']);
            $table->index(['recipient_id', 'created_at']);
            $table->index(['recipient_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
