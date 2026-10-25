<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Reports;

use App\Models\User;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Totais do cabecalho das filas de contas a pagar e a receber.
 *
 * Usa os mesmos scopes do dashboard e dos relatorios, para que os numeros do topo
 * da fila nunca divirjam dos cards.
 */
class BuildInstallmentsQueueSummary
{
    public function __construct(private readonly FinancialCompanyAccess $companyAccess) {}

    /**
     * @return array{
     *     receivable: array{amount: int, count: int},
     *     payable: array{amount: int, count: int},
     *     projected_balance: int,
     *     overdue_count: int
     * }
     */
    public function handle(CarbonImmutable $monthStart, ?int $companyId = null, ?User $user = null): array
    {
        $today = today()->toDateString();
        $receivable = FinancialDirection::Receivable->value;
        $payable = FinancialDirection::Payable->value;

        $query = FinancialInstallment::query();

        if ($user instanceof User) {
            $query = $this->companyAccess->scope($query, $user);
        }

        $summary = $query
            ->open()
            // Half-open range: SQLite stores date casts with a time part.
            ->where('due_date', '>=', $monthStart->toDateString())
            ->where('due_date', '<', $monthStart->addMonthNoOverflow()->toDateString())
            ->when(
                $companyId !== null,
                fn (Builder $query): Builder => $query->where('company_id', $companyId),
            )
            ->toBase()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN direction = ? THEN balance_amount ELSE 0 END), 0) AS receivable_amount,
                 COALESCE(SUM(CASE WHEN direction = ? THEN 1 ELSE 0 END), 0) AS receivable_count,
                 COALESCE(SUM(CASE WHEN direction = ? THEN balance_amount ELSE 0 END), 0) AS payable_amount,
                 COALESCE(SUM(CASE WHEN direction = ? THEN 1 ELSE 0 END), 0) AS payable_count,
                 COALESCE(SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END), 0) AS overdue_count',
                [$receivable, $receivable, $payable, $payable, $today],
            )
            ->first();

        $receivableAmount = (int) ($summary?->receivable_amount ?? 0);
        $payableAmount = (int) ($summary?->payable_amount ?? 0);

        return [
            'receivable' => [
                'amount' => $receivableAmount,
                'count' => (int) ($summary?->receivable_count ?? 0),
            ],
            'payable' => [
                'amount' => $payableAmount,
                'count' => (int) ($summary?->payable_count ?? 0),
            ],
            'projected_balance' => $receivableAmount - $payableAmount,
            'overdue_count' => (int) ($summary?->overdue_count ?? 0),
        ];
    }
}
