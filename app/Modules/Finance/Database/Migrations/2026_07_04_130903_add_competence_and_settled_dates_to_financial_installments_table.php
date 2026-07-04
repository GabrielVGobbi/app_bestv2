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
        Schema::table('financial_installments', function (Blueprint $table): void {
            if (! Schema::hasColumn('financial_installments', 'competence_date')) {
                $table->date('competence_date')->nullable()->index();
            }

            if (! Schema::hasColumn('financial_installments', 'settled_at')) {
                $table->timestamp('settled_at')->nullable()->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_installments', function (Blueprint $table): void {
            if (Schema::hasColumn('financial_installments', 'competence_date')) {
                $table->dropColumn('competence_date');
            }

            if (Schema::hasColumn('financial_installments', 'settled_at')) {
                $table->dropColumn('settled_at');
            }
        });
    }
};
