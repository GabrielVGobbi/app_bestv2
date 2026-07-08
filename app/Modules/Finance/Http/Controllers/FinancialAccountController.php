<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Http\Requests\FinancialAccount\StoreFinancialAccountRequest;
use App\Modules\Finance\Http\Requests\FinancialAccount\UpdateFinancialAccountRequest;
use App\Modules\Finance\Http\Resources\FinancialAccountResource;
use App\Modules\Finance\Models\FinancialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinancialAccountController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', FinancialAccount::class);

        return Inertia::render('app/financial-accounts/index', [
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', FinancialAccount::class);

        return Inertia::render('app/financial-accounts/create', [
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function store(StoreFinancialAccountRequest $request): RedirectResponse
    {
        $financialAccount = FinancialAccount::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Conta financeira criada.')]);

        return to_route('financial-accounts.show', $financialAccount);
    }

    public function show(FinancialAccount $financialAccount): Response
    {
        Gate::authorize('view', $financialAccount);

        return Inertia::render('app/financial-accounts/show', [
            'financialAccountData' => new FinancialAccountResource($financialAccount),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function edit(FinancialAccount $financialAccount): Response
    {
        Gate::authorize('update', $financialAccount);

        return Inertia::render('app/financial-accounts/edit', [
            'financialAccountData' => new FinancialAccountResource($financialAccount),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function update(UpdateFinancialAccountRequest $request, FinancialAccount $financialAccount): RedirectResponse
    {
        $financialAccount->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Conta financeira atualizada.')]);

        return to_route('financial-accounts.show', $financialAccount);
    }

    public function updateStatus(Request $request, FinancialAccount $financialAccount): RedirectResponse
    {
        Gate::authorize('update', $financialAccount);

        $validated = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $financialAccount->update([
            'active' => (bool) $validated['active'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status da conta financeira atualizado.')]);

        return back();
    }

    public function destroy(FinancialAccount $financialAccount): RedirectResponse
    {
        Gate::authorize('delete', $financialAccount);

        if ($financialAccount->movements()->exists()
            || $financialAccount->settlements()->exists()
            || $financialAccount->bankStatementImports()->exists()
            || $financialAccount->bankStatementLines()->exists()) {
            return back()->withErrors([
                'financial_account' => 'Conta financeira com movimentos, baixas ou extratos nao pode ser excluida.',
            ]);
        }

        $financialAccount->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Conta financeira excluida.')]);

        return to_route('financial-accounts.index', [], 303);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function typeOptions(): array
    {
        return collect(FinancialAccountType::cases())
            ->map(fn (FinancialAccountType $type): array => [
                'label' => match ($type) {
                    FinancialAccountType::BankAccount => 'Conta bancaria',
                    FinancialAccountType::Cash => 'Caixa',
                    FinancialAccountType::Wallet => 'Carteira',
                    FinancialAccountType::PaymentGateway => 'Gateway de pagamento',
                },
                'value' => $type->value,
            ])
            ->values()
            ->all();
    }
}
