<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $confirmedMatches = DB::table('reconciliation_matches')->where('status', 'confirmed');

        $hasDuplicateLines = (clone $confirmedMatches)
            ->select('bank_statement_line_id')
            ->groupBy('bank_statement_line_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        $hasDuplicateMovements = (clone $confirmedMatches)
            ->select('financial_movement_id')
            ->groupBy('financial_movement_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateLines || $hasDuplicateMovements) {
            throw new RuntimeException('Confirmed reconciliation conflicts must be resolved before adding exclusivity guards.');
        }

        $confirmedMatches->update([
            'confirmed_bank_statement_line_id' => DB::raw('bank_statement_line_id'),
            'confirmed_financial_movement_id' => DB::raw('financial_movement_id'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('reconciliation_matches')->update([
            'confirmed_bank_statement_line_id' => null,
            'confirmed_financial_movement_id' => null,
        ]);
    }
};
