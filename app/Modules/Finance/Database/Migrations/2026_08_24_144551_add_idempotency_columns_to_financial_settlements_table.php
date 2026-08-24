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
        Schema::table('financial_settlements', function (Blueprint $table): void {
            $table->string('idempotency_key', 100)->nullable()->after('origin');
            $table->char('idempotency_fingerprint', 64)->nullable()->after('idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_settlements', function (Blueprint $table): void {
            $table->dropColumn(['idempotency_key', 'idempotency_fingerprint']);
        });
    }
};
