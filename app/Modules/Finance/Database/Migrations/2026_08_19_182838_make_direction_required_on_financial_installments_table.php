<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha a direcao da parcela como obrigatoria.
 *
 * E a garantia de integridade que impede uma parcela sem direcao de escapar das
 * filas de contas a pagar e a receber, que sempre filtram por esse eixo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('financial_installments')->whereNull('direction')->count();

        if ($orphans > 0) {
            throw new RuntimeException(
                "Existem {$orphans} parcelas sem direcao. Rode o backfill antes de tornar a coluna obrigatoria."
            );
        }

        Schema::table('financial_installments', function (Blueprint $table): void {
            $table->string('direction', 32)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('financial_installments', function (Blueprint $table): void {
            $table->string('direction', 32)->nullable()->change();
        });
    }
};
