<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Reports;

use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Enums\FinancialSettlementStatus;
use App\Modules\Finance\Models\BankStatementLine;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialSettlement;
use Illuminate\Support\Carbon;

class BuildFinancialDashboardReport
{
    /**
     * @return array<string, mixed>
     */
    public function handle(?int $companyId = null, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();

        return [
            'cards' => [
                'open_receivables' => $this->card('Aberto a receber', $this->openAmount(FinancialDirection::Receivable, $companyId)),
                'overdue_receivables' => $this->card('Vencido a receber', $this->overdueAmount(FinancialDirection::Receivable, $companyId, $today)),
                'open_payables' => $this->card('Aberto a pagar', $this->openAmount(FinancialDirection::Payable, $companyId)),
                'overdue_payables' => $this->card('Vencido a pagar', $this->overdueAmount(FinancialDirection::Payable, $companyId, $today)),
                'monthly_settlements' => $this->card('Baixas do mes', $this->monthlySettlements($companyId, $today)),
                'pending_reconciliation' => $this->card('Conciliacoes pendentes', $this->pendingReconciliation($companyId)),
            ],
            'account_balances' => $this->accountBalances($companyId),
        ];
    }

    /**
     * @return array{label: string, amount: int}
     */
    private function card(string $label, int $amount): array
    {
        return ['label' => $label, 'amount' => $amount];
    }

    private function openAmount(FinancialDirection $direction, ?int $companyId): int
    {
        return (int) $this->baseInstallments($direction, $companyId)->sum('balance_amount');
    }

    private function overdueAmount(FinancialDirection $direction, ?int $companyId, Carbon $today): int
    {
        return (int) $this->baseInstallments($direction, $companyId)
            ->whereDate('due_date', '<', $today->toDateString())
            ->sum('balance_amount');
    }

    private function monthlySettlements(?int $companyId, Carbon $today): int
    {
        return (int) FinancialSettlement::query()
            ->where('status', FinancialSettlementStatus::Posted)
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->whereBetween('settled_at', [
                $today->copy()->startOfMonth(),
                $today->copy()->endOfMonth(),
            ])
            ->sum('net_amount');
    }

    private function pendingReconciliation(?int $companyId): int
    {
        return (int) BankStatementLine::query()
            ->whereNull('reconciled_at')
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->count();
    }

    /**
     * @return array<int, array{id: int, uuid: string, name: string, type_label: string, current_balance_amount: int, currency: string, active: bool}>
     */
    private function accountBalances(?int $companyId): array
    {
        return FinancialAccount::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->active()
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (FinancialAccount $account): array => [
                'id' => $account->id,
                'uuid' => $account->uuid,
                'name' => $account->displayName(),
                'type_label' => $account->typeLabel(),
                'current_balance_amount' => (int) $account->current_balance_amount,
                'currency' => $account->currency,
                'active' => $account->active,
            ])
            ->all();
    }

    private function baseInstallments(FinancialDirection $direction, ?int $companyId)
    {
        return FinancialInstallment::query()
            ->where('balance_amount', '>', 0)
            ->whereNotIn('status', [
                FinancialEntryStatus::Canceled->value,
                FinancialEntryStatus::Reversed->value,
                FinancialEntryStatus::Renegotiated->value,
            ])
            ->whereHas('financialEntry', function ($query) use ($direction, $companyId): void {
                $query->where('direction', $direction);

                if ($companyId !== null) {
                    $query->where('company_id', $companyId);
                }
            });
    }
}
