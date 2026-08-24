<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Actions\Entries\CancelFinancialEntry;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Actions\Reports\BuildInstallmentsQueueSummary;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Http\Requests\FinancialAgendaIndexRequest;
use App\Modules\Finance\Http\Requests\FinancialEntry\CancelFinancialEntryRequest;
use App\Modules\Finance\Http\Requests\FinancialEntry\StoreFinancialEntryRequest;
use App\Modules\Finance\Http\Resources\FinancialEntryResource;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinancialEntryController extends Controller
{
    public function index(
        FinancialAgendaIndexRequest $request,
        BuildInstallmentsQueueSummary $buildSummary,
    ): Response {
        Gate::authorize('viewAny', FinancialInstallment::class);

        $monthStart = $request->monthStart();

        return Inertia::render('app/financial-entries/index', [
            'direction' => $request->agendaDirection(),
            'period' => [
                'month' => $monthStart->format('Y-m'),
                'starts_at' => $monthStart->toDateString(),
                'ends_at' => $monthStart->endOfMonth()->toDateString(),
            ],
            'summary' => $buildSummary->handle($monthStart, user: $request->user()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', FinancialEntry::class);

        return Inertia::render('app/financial-entries/create', [
            'directionOptions' => $this->directionOptions(),
            'amountAdjustmentOptions' => $this->amountAdjustmentOptions(),
            'counterpartyTypeOptions' => $this->counterpartyTypeOptions(),
            'counterpartyTypeByDirection' => [
                FinancialDirection::Receivable->value => Client::class,
                FinancialDirection::Payable->value => Supplier::class,
            ],
        ]);
    }

    public function store(StoreFinancialEntryRequest $request, CreateManualFinancialEntry $createManualFinancialEntry): RedirectResponse
    {
        $entry = $createManualFinancialEntry->handle($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lancamento financeiro criado.')]);

        return to_route('financial-entries.show', $entry);
    }

    public function cancel(CancelFinancialEntryRequest $request, FinancialEntry $financialEntry, CancelFinancialEntry $cancelFinancialEntry): RedirectResponse
    {
        $cancelFinancialEntry->handle($financialEntry, $request->reason());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lançamento financeiro cancelado.')]);

        return back();
    }

    public function show(FinancialEntry $financialEntry): Response
    {
        Gate::authorize('view', $financialEntry);

        return Inertia::render('app/financial-entries/show', [
            'entryData' => new FinancialEntryResource(
                $financialEntry->load([
                    'category',
                    'counterparty',
                    'amountComponents',
                    'installments.settlementAllocations',
                    'settlements.allocations',
                    'settlements.financialAccount',
                    'settlements.movements.financialAccount',
                    'settlements.paymentMethod',
                ])
            ),
        ]);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function directionOptions(): array
    {
        return [
            ['label' => 'A receber', 'value' => FinancialDirection::Receivable->value],
            ['label' => 'A pagar', 'value' => FinancialDirection::Payable->value],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, operation: ?string, requires_description: bool}>
     */
    private function amountAdjustmentOptions(): array
    {
        return collect(FinancialAmountComponentCode::cases())
            ->filter(fn (FinancialAmountComponentCode $code): bool => $code->isAvailableAsManualAdjustment())
            ->map(fn (FinancialAmountComponentCode $code): array => [
                'label' => $code->label(),
                'value' => $code->value,
                'operation' => $code->fixedOperation()?->value,
                'requires_description' => $code->requiresDescription(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function counterpartyTypeOptions(): array
    {
        return [
            ['label' => 'Cliente', 'value' => Client::class],
            ['label' => 'Fornecedor', 'value' => Supplier::class],
        ];
    }
}
