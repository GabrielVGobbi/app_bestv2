<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promove a parcela a fonte operacional do fluxo de caixa.
 *
 * Denormaliza direcao, contraparte e categoria vindas do lancamento para que a
 * fila de contas a pagar/receber filtre e ordene sem `whereHas`, e cria os
 * acumuladores de desconto, juros e multa aplicados nas baixas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_installments', function (Blueprint $table): void {
            if (! Schema::hasColumn('financial_installments', 'direction')) {
                $table->string('direction', 32)->nullable()->after('status');
            }

            if (! Schema::hasColumn('financial_installments', 'counterparty_type')) {
                $table->nullableMorphs('counterparty');
            }

            if (! Schema::hasColumn('financial_installments', 'category_id')) {
                $table->foreignId('category_id')->nullable()->constrained('financial_categories')->nullOnDelete();
            }

            if (! Schema::hasColumn('financial_installments', 'description')) {
                $table->string('description')->nullable()->after('installment_number');
            }

            if (! Schema::hasColumn('financial_installments', 'discount_amount')) {
                $table->unsignedBigInteger('discount_amount')->default(0)->after('balance_amount');
            }

            if (! Schema::hasColumn('financial_installments', 'interest_amount')) {
                $table->unsignedBigInteger('interest_amount')->default(0)->after('discount_amount');
            }

            if (! Schema::hasColumn('financial_installments', 'penalty_amount')) {
                $table->unsignedBigInteger('penalty_amount')->default(0)->after('interest_amount');
            }
        });

        Schema::table('financial_installments', function (Blueprint $table): void {
            $table->index(['company_id', 'direction', 'status', 'due_date'], 'financial_installments_queue_index');
            $table->index(['company_id', 'direction', 'competence_date'], 'financial_installments_competence_index');
            $table->index(['company_id', 'category_id'], 'financial_installments_category_index');
        });
    }

    public function down(): void
    {
        Schema::table('financial_installments', function (Blueprint $table): void {
            $table->dropIndex('financial_installments_queue_index');
            $table->dropIndex('financial_installments_competence_index');
            $table->dropIndex('financial_installments_category_index');
        });

        Schema::table('financial_installments', function (Blueprint $table): void {
            if (Schema::hasColumn('financial_installments', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }

            if (Schema::hasColumn('financial_installments', 'counterparty_type')) {
                $table->dropMorphs('counterparty');
            }

            foreach (['direction', 'description', 'discount_amount', 'interest_amount', 'penalty_amount'] as $column) {
                if (Schema::hasColumn('financial_installments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
