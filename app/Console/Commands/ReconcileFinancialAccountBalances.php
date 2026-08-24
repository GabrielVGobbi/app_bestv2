<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\Accounts\ReconcileFinancialAccountBalance;
use App\Modules\Finance\Models\FinancialAccount;
use Illuminate\Console\Command;

class ReconcileFinancialAccountBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:check-balances
        {--account= : ID da conta financeira}
        {--company= : Limita a uma empresa}
        {--rebuild : Corrige o saldo quando permitido pelo ambiente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Confere saldos financeiros por abertura e movimentos; dry-run por padrão';

    /**
     * Execute the console command.
     */
    public function handle(ReconcileFinancialAccountBalance $reconcileBalance): int
    {
        $query = FinancialAccount::query()
            ->when($this->option('account'), fn ($query) => $query->whereKey((int) $this->option('account')))
            ->when($this->option('company'), fn ($query) => $query->where('company_id', (int) $this->option('company')))
            ->orderBy('id');
        $rebuild = (bool) $this->option('rebuild');
        $rows = [];
        $hasDrift = false;

        $query->eachById(function (FinancialAccount $account) use ($reconcileBalance, $rebuild, &$rows, &$hasDrift): void {
            $result = $reconcileBalance->handle($account, $rebuild);
            $hasDrift = $hasDrift || $result['drift_amount'] !== 0;
            $rows[] = [
                $account->id,
                $account->name,
                $result['expected_balance_amount'],
                $result['current_balance_amount'],
                $result['drift_amount'],
                $result['rebuilt'] ? 'reconstruído' : 'inalterado',
            ];
        });

        $this->table(['ID', 'Conta', 'Esperado', 'Atual', 'Drift', 'Ação'], $rows);
        $this->components->info($rebuild ? 'Reconstrução concluída.' : 'Dry-run concluído; nenhum saldo foi alterado.');

        return $hasDrift ? self::FAILURE : self::SUCCESS;
    }
}
