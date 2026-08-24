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
        Schema::table('reconciliation_matches', function (Blueprint $table): void {
            $table->unsignedBigInteger('confirmed_bank_statement_line_id')->nullable()->after('status');
            $table->unsignedBigInteger('confirmed_financial_movement_id')->nullable()->after('confirmed_bank_statement_line_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_matches', function (Blueprint $table): void {
            $table->dropColumn([
                'confirmed_bank_statement_line_id',
                'confirmed_financial_movement_id',
            ]);
        });
    }
};
