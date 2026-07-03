<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Enums\PaymentMethodType;
use App\Modules\Finance\Http\Requests\PaymentMethod\StorePaymentMethodRequest;
use App\Modules\Finance\Http\Requests\PaymentMethod\UpdatePaymentMethodRequest;
use App\Modules\Finance\Http\Resources\PaymentMethodResource;
use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', PaymentMethod::class);

        return Inertia::render('app/payment-methods/index', [
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', PaymentMethod::class);

        return Inertia::render('app/payment-methods/create', [
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        $paymentMethod = PaymentMethod::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Metodo de pagamento criado.')]);

        return to_route('payment-methods.show', $paymentMethod);
    }

    public function show(PaymentMethod $paymentMethod): Response
    {
        Gate::authorize('view', $paymentMethod);

        return Inertia::render('app/payment-methods/show', [
            'paymentMethodData' => new PaymentMethodResource($paymentMethod),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function edit(PaymentMethod $paymentMethod): Response
    {
        Gate::authorize('update', $paymentMethod);

        return Inertia::render('app/payment-methods/edit', [
            'paymentMethodData' => new PaymentMethodResource($paymentMethod),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Metodo de pagamento atualizado.')]);

        return to_route('payment-methods.show', $paymentMethod);
    }

    public function updateStatus(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        Gate::authorize('update', $paymentMethod);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $paymentMethod->update([
            'is_active' => (bool) $validated['is_active'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status do metodo de pagamento atualizado.')]);

        return back();
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        Gate::authorize('delete', $paymentMethod);

        $paymentMethod->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Metodo de pagamento excluido.')]);

        return to_route('payment-methods.index', [], 303);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function typeOptions(): array
    {
        return collect(PaymentMethodType::cases())
            ->map(fn (PaymentMethodType $type): array => [
                'label' => match ($type) {
                    PaymentMethodType::Cash => 'Dinheiro',
                    PaymentMethodType::Pix => 'Pix',
                    PaymentMethodType::BankTransfer => 'Transferencia bancaria',
                    PaymentMethodType::Boleto => 'Boleto',
                    PaymentMethodType::CreditCard => 'Cartao de credito',
                    PaymentMethodType::DebitCard => 'Cartao de debito',
                    PaymentMethodType::Other => 'Outro',
                },
                'value' => $type->value,
            ])
            ->values()
            ->all();
    }
}
