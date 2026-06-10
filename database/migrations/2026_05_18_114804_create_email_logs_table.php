<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->string('to_email')->index();
            $table->string('to_name')->nullable();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('subject');

            $table->string('template');
            $table->json('variables')->nullable();

            $table->string('category')->index();

            // queued, processing, sent, failed, bounced, delivered
            $table->string('status')->default('queued')->index();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->nullableMorphs('notifiable');

            // Prevents duplicate sends on retry: hash(to + template + notifiable_id + date)
            $table->string('idempotency_key')->nullable()->unique();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Which Laravel mailer driver was used (e.g., 'resend', 'smtp', 'ses')
            $table->string('mailer')->nullable();

            // SMTP Message-ID or provider message ID, used for webhook delivery tracking
            $table->string('message_id')->nullable()->index();

            // Provider-specific metadata (e.g., webhook payload, bounce reason)
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['category', 'status']);
            $table->index(['to_email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
