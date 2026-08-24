<?php

use App\Modules\Finance\Models\BankStatementLine;
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
        Schema::table('bank_statement_lines', function (Blueprint $table): void {
            $table->longText('protected_payload')->nullable()->after('raw_payload');
        });

        BankStatementLine::withoutEvents(function (): void {
            BankStatementLine::query()->whereNotNull('raw_payload')->orderBy('id')->eachById(
                function (BankStatementLine $line): void {
                    $line->forceFill([
                        'protected_payload' => $line->raw_payload,
                        'raw_payload' => null,
                    ])->save();
                },
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        BankStatementLine::withoutEvents(function (): void {
            BankStatementLine::query()->whereNotNull('protected_payload')->orderBy('id')->eachById(
                function (BankStatementLine $line): void {
                    $line->forceFill([
                        'raw_payload' => $line->protected_payload,
                        'protected_payload' => null,
                    ])->save();
                },
            );
        });

        Schema::table('bank_statement_lines', function (Blueprint $table): void {
            $table->dropColumn('protected_payload');
        });
    }
};
