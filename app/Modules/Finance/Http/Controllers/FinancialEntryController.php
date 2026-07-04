<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Http\Requests\FinancialEntry\StoreFinancialEntryRequest;
use App\Modules\Finance\Http\Resources\FinancialEntryResource;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinancialEntryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', FinancialEntry::class);

        return Inertia::render('app/financial-entries/index', [
            'directionOptions' => $this->directionOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', FinancialEntry::class);

        return Inertia::render('app/financial-entries/create', [
            'directionOptions' => $this->directionOptions(),
            'componentCodeOptions' => $this->componentCodeOptions(),
            'componentOperationOptions' => $this->componentOperationOptions(),
            'counterpartyTypeOptions' => $this->counterpartyTypeOptions(),
        ]);
    }

    public function store(StoreFinancialEntryRequest $request, CreateManualFinancialEntry $createManualFinancialEntry): RedirectResponse
    {
        $entry = $createManualFinancialEntry->handle($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lancamento financeiro criado.')]);

        return to_route('financial-entries.show', $entry);
    }

    public function show(FinancialEntry $financialEntry): Response
    {
        Gate::authorize('view', $financialEntry);

        return Inertia::render('app/financial-entries/show', [
            'entryData' => new FinancialEntryResource(
                $financialEntry->load(['category', 'counterparty', 'amountComponents', 'installments'])
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
     * @return array<int, array{label: string, value: string}>
     */
    private function componentCodeOptions(): array
    {
        return collect(FinancialAmountComponentCode::cases())
            ->map(fn (FinancialAmountComponentCode $code): array => [
                'label' => match ($code) {
                    FinancialAmountComponentCode::Principal => 'Principal',
                    FinancialAmountComponentCode::Discount => 'Desconto',
                    FinancialAmountComponentCode::Tax => 'Imposto',
                    FinancialAmountComponentCode::Interest => 'Juros',
                    FinancialAmountComponentCode::Penalty => 'Multa',
                    FinancialAmountComponentCode::Fee => 'Taxa',
                    FinancialAmountComponentCode::Withholding => 'Retenção',
                    FinancialAmountComponentCode::Adjustment => 'Ajuste',
                    FinancialAmountComponentCode::Other => 'Outro',
                },
                'value' => $code->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function componentOperationOptions(): array
    {
        return [
            ['label' => 'Soma', 'value' => FinancialAmountOperation::Add->value],
            ['label' => 'Subtrai', 'value' => FinancialAmountOperation::Subtract->value],
        ];
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
