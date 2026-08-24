<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Reports;

use App\Models\User;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialSettlementStatus;
use App\Modules\Finance\Models\BankStatementLine;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialSettlement;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BuildFinancialDashboardReport
{
    public function __construct(private readonly FinancialCompanyAccess $companyAccess) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(?int $companyId = null, ?Carbon $today = null, ?User $user = null): array
    {
        $today ??= Carbon::today();

        return [
            'cards' => [
                'open_receivables' => $this->card('Aberto a receber', $this->openAmount(FinancialDirection::Receivable, $companyId, $user)),
                'overdue_receivables' => $this->card('Vencido a receber', $this->overdueAmount(FinancialDirection::Receivable, $companyId, $today, $user)),
                'open_payables' => $this->card('Aberto a pagar', $this->openAmount(FinancialDirection::Payable, $companyId, $user)),
                'overdue_payables' => $this->card('Vencido a pagar', $this->overdueAmount(FinancialDirection::Payable, $companyId, $today, $user)),
                'monthly_settlements' => $this->card('Baixas do mes', $this->monthlySettlements($companyId, $today, $user)),
                'pending_reconciliation' => $this->card('Conciliacoes pendentes', $this->pendingReconciliation($companyId, $user)),
            ],
            'account_balances' => $this->accountBalances($companyId, $user),
        ];
    }

    /**
     * @return array{label: string, amount: int}
     */
    private function card(string $label, int $amount): array
    {
        return ['label' => $label, 'amount' => $amount];
    }

    private function openAmount(FinancialDirection $direction, ?int $companyId, ?User $user): int
    {
        return (int) $this->baseInstallments($direction, $companyId, $user)->open()->sum('balance_amount');
    }

    private function overdueAmount(FinancialDirection $direction, ?int $companyId, Carbon $today, ?User $user): int
    {
        return (int) $this->baseInstallments($direction, $companyId, $user)
            ->overdue($today->toDateString())
            ->sum('balance_amount');
    }

    private function monthlySettlements(?int $companyId, Carbon $today, ?User $user): int
    {
        $query = FinancialSettlement::query();

        if ($user instanceof User) {
            $query = $this->companyAccess->scope($query, $user);
        }

        return (int) $query
            ->where('status', FinancialSettlementStatus::Posted)
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->whereBetween('settled_at', [
                $today->copy()->startOfMonth(),
                $today->copy()->endOfMonth(),
            ])
            ->sum('net_amount');
    }

    private function pendingReconciliation(?int $companyId, ?User $user): int
    {
        $query = BankStatementLine::query();

        if ($user instanceof User) {
            $query = $this->companyAccess->scope($query, $user);
        }

        return (int) $query
            ->whereNull('reconciled_at')
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->count();
    }

    /**
     * @return array<int, array{id: int, uuid: string, name: string, type_label: string, current_balance_amount: int, currency: string, active: bool}>
     */
    private function accountBalances(?int $companyId, ?User $user): array
    {
        $query = FinancialAccount::query();

        if ($user instanceof User) {
            $query = $this->companyAccess->scope($query, $user);
        }

        return $query
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

    /**
     * Base de parcelas da direcao, sem ainda decidir o que e aberto ou vencido.
     *
     * Usa as colunas denormalizadas da propria parcela em vez de `whereHas`,
     * para bater no indice `financial_installments_queue_index`.
     *
     * @return Builder<FinancialInstallment>
     */
    private function baseInstallments(FinancialDirection $direction, ?int $companyId, ?User $user): Builder
    {
        $query = FinancialInstallment::query();

        if ($user instanceof User) {
            $query = $this->companyAccess->scope($query, $user);
        }

        return $query
            ->direction($direction)
            ->when($companyId !== null, fn (Builder $query): Builder => $query->where('company_id', $companyId));
    }
}
