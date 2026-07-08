<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Modules\Finance\Http\Requests\FinancialCategory\StoreFinancialCategoryRequest;
use App\Modules\Finance\Http\Requests\FinancialCategory\UpdateFinancialCategoryRequest;
use App\Modules\Finance\Http\Resources\FinancialCategoryResource;
use App\Modules\Finance\Models\FinancialCategory;
use App\Supports\Api\ApiDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class FinancialCategoryApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', FinancialCategory::class);

        $query = FinancialCategory::query()
            ->with('parent')
            ->withCount('children');

        return ApiDisplay::for(FinancialCategory::class, FinancialCategoryResource::class)
            ->usingQuery($query)
            ->paginate($request);
    }

    public function tree(Request $request): ResourceCollection
    {
        Gate::authorize('viewAny', FinancialCategory::class);

        $validated = $request->validate([
            'type' => ['nullable', 'string'],
            'company_id' => ['nullable', 'integer'],
            'active' => ['nullable', 'in:true,false,1,0'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $categories = FinancialCategory::query()
            ->with('parent')
            ->when(
                filled($validated['type'] ?? null),
                fn (Builder $query) => $query->where('type', $validated['type'])
            )
            ->when(
                filled($validated['company_id'] ?? null),
                fn (Builder $query) => $query->forCompany((int) $validated['company_id'])
            )
            ->when(
                $request->boolean('active'),
                fn (Builder $query) => $query->active()
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $search = trim((string) ($validated['search'] ?? ''));

        if ($search !== '') {
            $categories = $categories->filter(
                fn (FinancialCategory $category): bool => str($category->name)->lower()->contains(str($search)->lower())
                    || str($category->fullPath())->lower()->contains(str($search)->lower())
            )->values();
        }

        return FinancialCategoryResource::collection($this->buildTree($categories));
    }

    public function store(StoreFinancialCategoryRequest $request): FinancialCategoryResource
    {
        $category = FinancialCategory::query()->create($request->validated());

        return new FinancialCategoryResource($category->load(['parent', 'children']));
    }

    public function show(FinancialCategory $financialCategory): FinancialCategoryResource
    {
        Gate::authorize('view', $financialCategory);

        return new FinancialCategoryResource($financialCategory->load(['parent', 'children']));
    }

    public function update(UpdateFinancialCategoryRequest $request, FinancialCategory $financialCategory): FinancialCategoryResource
    {
        $financialCategory->update($request->validated());

        return new FinancialCategoryResource($financialCategory->load(['parent', 'children']));
    }

    public function destroy(FinancialCategory $financialCategory): JsonResponse
    {
        Gate::authorize('delete', $financialCategory);
        abort_if(! $financialCategory->canBeDeleted(), 403);

        $financialCategory->delete();

        return response()->json(null, 204);
    }

    /**
     * @param  Collection<int, FinancialCategory>  $categories
     * @return Collection<int, FinancialCategory>
     */
    private function buildTree(Collection $categories): Collection
    {
        $byParent = $categories->groupBy(fn (FinancialCategory $category): int => (int) ($category->parent_id ?? 0));

        $attachChildren = function (FinancialCategory $category) use (&$attachChildren, $byParent): FinancialCategory {
            $children = ($byParent->get((int) $category->id) ?? collect())
                ->map(fn (FinancialCategory $child): FinancialCategory => $attachChildren($child))
                ->values();

            $category->setRelation('children', $children);

            return $category;
        };

        return ($byParent->get(0) ?? collect())
            ->map(fn (FinancialCategory $category): FinancialCategory => $attachChildren($category))
            ->values();
    }
}
