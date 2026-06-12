<?php

declare(strict_types=1);

namespace App\Modules\Services\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Modules\Services\Http\Requests\Service\StoreServiceRequest;
use App\Modules\Services\Http\Requests\Service\UpdateServiceRequest;
use App\Modules\Services\Http\Resources\ServiceResource;
use App\Modules\Services\Models\Service;
use App\Supports\Api\ApiDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ServiceApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Service::class);

        return ApiDisplay::for(Service::class, ServiceResource::class)->paginate($request);
    }

    public function store(StoreServiceRequest $request): ServiceResource
    {
        $service = Service::create($request->validated());

        return new ServiceResource($service);
    }

    public function show(Service $service): ServiceResource
    {
        Gate::authorize('view', $service);

        return new ServiceResource($service);
    }

    public function update(UpdateServiceRequest $request, Service $service): ServiceResource
    {
        $service->update($request->validated());

        return new ServiceResource($service);
    }

    public function destroy(Service $service): JsonResponse
    {
        Gate::authorize('delete', $service);

        $service->delete();

        return response()->json(null, 204);
    }

    public function restore(string $service): ServiceResource
    {
        $serviceModel = Service::withTrashed()->whereUuid($service)->firstOrFail();

        Gate::authorize('restore', $serviceModel);

        $serviceModel->restore();

        return new ServiceResource($serviceModel);
    }
}
