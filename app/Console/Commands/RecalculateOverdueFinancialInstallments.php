<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\Entries\RecalculateFinancialEntryStatus;
use App\Modules\Finance\Models\FinancialEntry;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RecalculateOverdueFinancialInstallments extends Command
{
    protected $signature = 'finance:recalculate-overdue
        {--company= : Recalcula apenas uma empresa}
        {--chunk=100 : Quantidade de lançamentos por lote}';

    protected $description = 'Recalcula status de parcelas e lançamentos financeiros vencidos.';

    public function handle(RecalculateFinancialEntryStatus $recalculate): int
    {
        $companyId = $this->option('company');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $count = 0;

        // Antes isto varria todo lancamento com parcela aberta, todas as noites. O
        // scope `overdue()` restringe ao que de fato passou do vencimento, que e a
        // unica coisa que este comando muda.
        FinancialEntry::query()
            ->when(filled($companyId), fn (Builder $query): Builder => $query->where('company_id', (int) $companyId))
            ->whereHas('installments', fn (Builder $query) => $query->overdue())
            ->chunkById($chunkSize, function ($entries) use ($recalculate, &$count): void {
                foreach ($entries as $entry) {
                    $recalculate->handle($entry, eagerLoad: false);
                    $count++;
                }
            });

        $this->info("Lançamentos financeiros recalculados: {$count}.");

        return self::SUCCESS;
    }
}
