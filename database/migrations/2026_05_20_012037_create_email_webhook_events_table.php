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
        Schema::create('email_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('email_log_id')->nullable()->constrained('email_logs')->nullOnDelete();
            $table->string('provider')->index();
            $table->string('event_id')->nullable();
            $table->string('event_type')->nullable()->index();
            $table->string('event_family')->nullable()->index();
            $table->string('message_id')->nullable()->index();
            $table->string('signature_id')->nullable()->index();
            $table->string('signature_timestamp')->nullable();
            $table->json('headers')->nullable();
            $table->json('payload');
            $table->longText('raw_payload')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamp('received_at')->index();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index(['provider', 'event_type', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_webhook_events');
    }
};
