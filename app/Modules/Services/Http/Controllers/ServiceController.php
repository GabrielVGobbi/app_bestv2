<?php

declare(strict_types=1);

namespace App\Modules\Services\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Services\Http\Requests\Service\QuickUpdateServiceRequest;
use App\Modules\Services\Http\Requests\Service\StoreServiceRequest;
use App\Modules\Services\Http\Requests\Service\UpdateServiceRequest;
use App\Modules\Services\Http\Resources\ServiceResource;
use App\Modules\Services\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Service::class);

        return Inertia::render('app/services/index', [
            'permissions' => $this->permissions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Service::class);

        return Inertia::render('app/services/create');
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $service = Service::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service created.')]);

        return to_route('services.show', $service);
    }

    public function show(Service $service): Response
    {
        Gate::authorize('view', $service);

        return Inertia::render('app/services/show', [
            'serviceData' => new ServiceResource($service),
            'permissions' => $this->permissions($service),
        ]);
    }

    public function edit(Service $service): Response
    {
        Gate::authorize('update', $service);

        return Inertia::render('app/services/edit', [
            'serviceData' => new ServiceResource($service),
            'permissions' => $this->permissions($service),
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service updated.')]);

        return to_route('services.show', $service);
    }

    public function quickUpdate(QuickUpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->updatePayload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service updated.')]);

        return back();
    }

    public function destroy(Service $service): RedirectResponse
    {
        Gate::authorize('delete', $service);

        $service->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service deleted.')]);

        return to_route('services.index', [], 303);
    }

    public function restore(string $service): RedirectResponse
    {
        $serviceModel = Service::withTrashed()->whereUuid($service)->firstOrFail();

        Gate::authorize('restore', $serviceModel);

        $serviceModel->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service restored.')]);

        return to_route('services.show', $serviceModel);
    }

    /**
     * @return array<string, bool>
     */
    private function permissions(?Service $service = null): array
    {
        $serviceModel = $service ?? new Service;

        return [
            'can_create' => auth()->user()?->can('create', Service::class) ?? false,
            'can_update' => auth()->user()?->can('update', $serviceModel) ?? false,
            'can_delete' => auth()->user()?->can('delete', $serviceModel) ?? false,
            'can_restore' => auth()->user()?->can('restore', $serviceModel) ?? false,
        ];
    }
}
