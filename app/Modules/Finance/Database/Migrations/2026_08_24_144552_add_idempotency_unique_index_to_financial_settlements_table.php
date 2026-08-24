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
            $table->unique(
                ['company_id', 'origin', 'idempotency_key'],
                'financial_settlements_idempotency_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_settlements', function (Blueprint $table): void {
            $table->dropUnique('financial_settlements_idempotency_unique');
        });
    }
};
