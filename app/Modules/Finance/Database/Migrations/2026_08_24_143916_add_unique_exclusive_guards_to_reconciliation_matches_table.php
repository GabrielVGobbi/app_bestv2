<?php

declare(strict_types=1);

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
        Schema::table('reconciliation_matches', function (Blueprint $table): void {
            $table->unique('confirmed_bank_statement_line_id', 'reconciliation_confirmed_line_unique');
            $table->unique('confirmed_financial_movement_id', 'reconciliation_confirmed_movement_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_matches', function (Blueprint $table): void {
            $table->dropUnique('reconciliation_confirmed_line_unique');
            $table->dropUnique('reconciliation_confirmed_movement_unique');
        });
    }
};
