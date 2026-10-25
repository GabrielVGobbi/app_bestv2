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
        $connection = Schema::getConnection();
        $previousZone = $connection->getDriverName() === 'mysql' ? $connection->scalar('SELECT @@session.time_zone') : null;
        if ($previousZone !== null) {
            $connection->statement("SET time_zone = '+00:00'");
        }

        try {
            Schema::table('email_webhook_events', function (Blueprint $table) {
                $table->char('payload_hash', 64)->nullable();
                $table->timestamp('authenticated_at')->nullable();
                $table->uuid('dispatch_token')->nullable();
                $table->timestamp('linked_at')->nullable();
                $table->string('processing_result', 48)->nullable();
                $table->dateTime('occurred_at', 6)->nullable()->change();
            });

            Schema::table('email_logs', function (Blueprint $table) {
                $table->dateTime('webhook_occurred_at', 6)->nullable();
            });
        } finally {
            if ($previousZone !== null) {
                $connection->statement('SET time_zone = ?', [$previousZone]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_webhook_events', function (Blueprint $table) {
            $table->dropColumn(['payload_hash', 'authenticated_at', 'dispatch_token', 'linked_at', 'processing_result']);
        });

        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropColumn('webhook_occurred_at');
        });
    }
};
