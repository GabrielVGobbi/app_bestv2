<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Copia direcao, contraparte e categoria do lancamento para as parcelas existentes.
 *
 * Migration de dados, separada da DDL de proposito. Percorre em lotes com
 * `chunkById` e usa o query builder em vez de Eloquent, para nao depender de
 * models que podem mudar depois desta migration ja ter rodado.
 *
 * E reexecutavel: so toca parcelas cuja direcao ainda esta nula.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('financial_entries')
            ->select(['id', 'direction', 'counterparty_type', 'counterparty_id', 'category_id'])
            ->orderBy('id')
            ->chunkById(500, function ($entries): void {
                foreach ($entries as $entry) {
                    DB::table('financial_installments')
                        ->where('financial_entry_id', $entry->id)
                        ->whereNull('direction')
                        ->update([
                            'direction' => $entry->direction,
                            'counterparty_type' => $entry->counterparty_type,
                            'counterparty_id' => $entry->counterparty_id,
                            'category_id' => $entry->category_id,
                        ]);
                }
            });
    }

    /**
     * Sem reversao de dados: as colunas sao derivadas do lancamento e a migration
     * de DDL anterior ja as remove por inteiro no rollback.
     */
    public function down(): void
    {
        //
    }
};
