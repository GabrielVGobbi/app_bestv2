<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Enums\FinancialCategoryType;
use App\Modules\Finance\Http\Requests\FinancialCategory\StoreFinancialCategoryRequest;
use App\Modules\Finance\Http\Requests\FinancialCategory\UpdateFinancialCategoryRequest;
use App\Modules\Finance\Http\Resources\FinancialCategoryResource;
use App\Modules\Finance\Models\FinancialCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinancialCategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', FinancialCategory::class);

        return Inertia::render('app/financial-categories/index', [
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', FinancialCategory::class);

        return Inertia::render('app/financial-categories/create', [
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function store(StoreFinancialCategoryRequest $request): RedirectResponse
    {
        $category = FinancialCategory::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Categoria financeira criada.')]);

        return to_route('financial-categories.show', $category);
    }

    public function show(FinancialCategory $financialCategory): Response
    {
        Gate::authorize('view', $financialCategory);

        return Inertia::render('app/financial-categories/show', [
            'categoryData' => new FinancialCategoryResource($financialCategory->load(['parent', 'children'])),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function edit(FinancialCategory $financialCategory): Response
    {
        Gate::authorize('update', $financialCategory);

        return Inertia::render('app/financial-categories/edit', [
            'categoryData' => new FinancialCategoryResource($financialCategory->load(['parent', 'children'])),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function update(UpdateFinancialCategoryRequest $request, FinancialCategory $financialCategory): RedirectResponse
    {
        $financialCategory->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Categoria financeira atualizada.')]);

        return to_route('financial-categories.show', $financialCategory);
    }

    public function updateStatus(Request $request, FinancialCategory $financialCategory): RedirectResponse
    {
        Gate::authorize('update', $financialCategory);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $financialCategory->update([
            'is_active' => (bool) $validated['is_active'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status da categoria financeira atualizado.')]);

        return back();
    }

    public function destroy(FinancialCategory $financialCategory): RedirectResponse
    {
        Gate::authorize('delete', $financialCategory);
        abort_if(! $financialCategory->canBeDeleted(), 403);

        $financialCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Categoria financeira excluida.')]);

        return to_route('financial-categories.index', [], 303);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function typeOptions(): array
    {
        return collect(FinancialCategoryType::cases())
            ->map(fn (FinancialCategoryType $type): array => [
                'label' => match ($type) {
                    FinancialCategoryType::Income => 'Receita',
                    FinancialCategoryType::Expense => 'Despesa',
                    FinancialCategoryType::Transfer => 'Transferencia',
                },
                'value' => $type->value,
            ])
            ->values()
            ->all();
    }
}
