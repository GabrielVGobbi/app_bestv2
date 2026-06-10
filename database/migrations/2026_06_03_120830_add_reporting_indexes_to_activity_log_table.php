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
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index(['causer_type', 'causer_id', 'created_at'], 'activity_log_causer_created_index');
            $table->index(['event', 'created_at'], 'activity_log_event_created_index');
            $table->index(['log_name', 'event', 'created_at'], 'activity_log_log_event_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_causer_created_index');
            $table->dropIndex('activity_log_event_created_index');
            $table->dropIndex('activity_log_log_event_created_index');
        });
    }
};
