<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Suppliers\Http\Requests\Supplier\StoreSupplierRequest;
use App\Modules\Suppliers\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Modules\Suppliers\Http\Resources\SupplierResource;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Supplier::class);

        return Inertia::render('app/suppliers/index');
    }

    public function create(): Response
    {
        Gate::authorize('create', Supplier::class);

        return Inertia::render('app/suppliers/create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fornecedor criado.')]);

        return to_route('suppliers.show', $supplier);
    }

    public function show(Supplier $supplier): Response
    {
        Gate::authorize('view', $supplier);

        return Inertia::render('app/suppliers/show', [
            'supplierData' => new SupplierResource($supplier),
            'timelineSubject' => [
                'type' => $supplier::timelineType(),
                'id' => $supplier->uuid,
            ],
        ]);
    }

    public function edit(Supplier $supplier): Response
    {
        Gate::authorize('update', $supplier);

        return Inertia::render('app/suppliers/edit', [
            'supplierData' => new SupplierResource($supplier),
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        Gate::authorize('update', $supplier);

        $supplier->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fornecedor atualizado.')]);

        return to_route('suppliers.show', $supplier);
    }

    public function updateStatus(Request $request, Supplier $supplier): RedirectResponse
    {
        Gate::authorize('update', $supplier);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
        ]);

        $supplier->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status do fornecedor atualizado.')]);

        return back();
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        Gate::authorize('delete', $supplier);
        abort_if(! $supplier->canBeDeleted(), 403);

        $supplier->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fornecedor removido.')]);

        return to_route('suppliers.index', [], 303);
    }
}
