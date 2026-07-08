<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\Entries\RecalculateFinancialEntryStatus;
use App\Modules\Finance\Enums\FinancialEntryStatus;
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

        FinancialEntry::query()
            ->when(filled($companyId), fn (Builder $query): Builder => $query->where('company_id', (int) $companyId))
            ->whereHas('installments', function (Builder $query): void {
                $query
                    ->where('balance_amount', '>', 0)
                    ->whereNotNull('due_date')
                    ->whereIn('status', [
                        FinancialEntryStatus::Open->value,
                        FinancialEntryStatus::Overdue->value,
                        FinancialEntryStatus::PartiallyPaid->value,
                    ]);
            })
            ->chunkById($chunkSize, function ($entries) use ($recalculate, &$count): void {
                foreach ($entries as $entry) {
                    $recalculate->handle($entry);
                    $count++;
                }
            });

        $this->info("Lançamentos financeiros recalculados: {$count}.");

        return self::SUCCESS;
    }
}
