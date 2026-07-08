<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('financial_settlements')
            ->whereNotNull('financial_account_id')
            ->whereNotIn('financial_account_id', DB::table('financial_accounts')->select('id'))
            ->update(['financial_account_id' => null]);

        DB::table('financial_movements')
            ->whereNotNull('financial_account_id')
            ->whereNotIn('financial_account_id', DB::table('financial_accounts')->select('id'))
            ->update(['financial_account_id' => null]);

        Schema::table('financial_settlements', function (Blueprint $table): void {
            $table->foreign('financial_account_id', 'financial_settlements_account_fk')
                ->references('id')
                ->on('financial_accounts')
                ->restrictOnDelete();
        });

        Schema::table('financial_movements', function (Blueprint $table): void {
            $table->foreign('financial_account_id', 'financial_movements_account_fk')
                ->references('id')
                ->on('financial_accounts')
                ->restrictOnDelete();
        });

        Schema::table('bank_statement_imports', function (Blueprint $table): void {
            $table->foreignId('financial_account_id')
                ->nullable()
                ->after('company_id')
                ->constrained('financial_accounts')
                ->restrictOnDelete();
        });

        Schema::table('bank_statement_lines', function (Blueprint $table): void {
            $table->dropUnique('bank_statement_lines_line_hash_unique');
            $table->foreignId('financial_account_id')
                ->nullable()
                ->after('company_id')
                ->constrained('financial_accounts')
                ->restrictOnDelete();
            $table->unique(['financial_account_id', 'line_hash'], 'bank_statement_lines_account_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bank_statement_lines', function (Blueprint $table): void {
            $table->dropUnique('bank_statement_lines_account_hash_unique');
            $table->dropConstrainedForeignId('financial_account_id');
            $table->unique('line_hash', 'bank_statement_lines_line_hash_unique');
        });

        Schema::table('bank_statement_imports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('financial_account_id');
        });

        Schema::table('financial_movements', function (Blueprint $table): void {
            $table->dropForeign('financial_movements_account_fk');
        });

        Schema::table('financial_settlements', function (Blueprint $table): void {
            $table->dropForeign('financial_settlements_account_fk');
        });
    }
};
