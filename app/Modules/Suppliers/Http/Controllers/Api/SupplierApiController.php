<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Modules\Suppliers\Http\Requests\Supplier\StoreSupplierRequest;
use App\Modules\Suppliers\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Modules\Suppliers\Http\Resources\SupplierResource;
use App\Modules\Suppliers\Models\Supplier;
use App\Supports\Api\ApiDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SupplierApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Supplier::class);

        return ApiDisplay::for(Supplier::class, SupplierResource::class)->paginate($request);
    }

    public function store(StoreSupplierRequest $request): SupplierResource
    {
        $supplier = Supplier::query()->create($request->validated());

        return new SupplierResource($supplier);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        Gate::authorize('view', $supplier);

        return new SupplierResource($supplier);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        Gate::authorize('update', $supplier);

        $supplier->update($request->validated());

        return new SupplierResource($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        Gate::authorize('delete', $supplier);
        abort_if(! $supplier->canBeDeleted(), 403);

        $supplier->delete();

        return response()->json(null, 204);
    }
}
