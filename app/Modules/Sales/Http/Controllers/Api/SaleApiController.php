<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Models\User;
use App\Modules\Sales\Http\Resources\SaleResource;
use App\Modules\Sales\Models\Sale;
use App\Supports\Api\ApiDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SaleApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Sale::class);

        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return ApiDisplay::for(Sale::class, SaleResource::class)
            ->usingQuery(
                Sale::query()
                    ->with(['company', 'client', 'seller'])
                    ->visibleTo($user)
            )
            ->paginate($request);
    }

    public function show(Sale $sale): SaleResource
    {
        Gate::authorize('view', $sale);

        return new SaleResource($sale->load(['company', 'client', 'seller']));
    }

    public function destroy(Sale $sale): JsonResponse
    {
        Gate::authorize('delete', $sale);

        $sale->delete();

        return response()->json(null, 204);
    }
}
