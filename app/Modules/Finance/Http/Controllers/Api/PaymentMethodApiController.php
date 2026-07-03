<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Modules\Finance\Http\Requests\PaymentMethod\StorePaymentMethodRequest;
use App\Modules\Finance\Http\Requests\PaymentMethod\UpdatePaymentMethodRequest;
use App\Modules\Finance\Http\Resources\PaymentMethodResource;
use App\Modules\Finance\Models\PaymentMethod;
use App\Supports\Api\ApiDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PaymentMethodApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PaymentMethod::class);

        return ApiDisplay::for(PaymentMethod::class, PaymentMethodResource::class)
            ->usingQuery(PaymentMethod::query())
            ->paginate($request);
    }

    public function options(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PaymentMethod::class);

        $validated = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:32'],
            'active' => ['nullable', 'boolean'],
        ]);

        $query = PaymentMethod::query()
            ->when(
                filled($validated['company_id'] ?? null),
                fn (Builder $query) => $query->forCompany((int) $validated['company_id'])
            )
            ->when(
                filled($validated['type'] ?? null),
                fn (Builder $query) => $query->where('type', $validated['type'])
            )
            ->when(
                $request->boolean('active'),
                fn (Builder $query) => $query->active()
            )
            ->orderBy('sort_order')
            ->orderBy('name');

        return PaymentMethodResource::collection($query->get());
    }

    public function store(StorePaymentMethodRequest $request): PaymentMethodResource
    {
        $paymentMethod = PaymentMethod::query()->create($request->validated());

        return new PaymentMethodResource($paymentMethod);
    }

    public function show(PaymentMethod $paymentMethod): PaymentMethodResource
    {
        Gate::authorize('view', $paymentMethod);

        return new PaymentMethodResource($paymentMethod);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): PaymentMethodResource
    {
        $paymentMethod->update($request->validated());

        return new PaymentMethodResource($paymentMethod);
    }

    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        Gate::authorize('delete', $paymentMethod);

        $paymentMethod->delete();

        return response()->json(null, 204);
    }
}
