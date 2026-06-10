<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Modules\Permissions\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        $query = Permission::query();

        $search = $request->searchTerm();
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('group', 'like', "%{$search}%");
            });
        }

        $sort = $request->sortColumn() ?? 'name';
        $direction = $request->sortDirection() ?? 'asc';
        $query->orderBy($sort, $direction);

        return PermissionResource::collection(
            $query->paginate($request->perPage())->withQueryString()
        );
    }

    public function store(StorePermissionRequest $request): PermissionResource
    {
        $permission = Permission::create($request->validated());

        return new PermissionResource($permission);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): PermissionResource
    {
        $permission->update($request->validated());

        return new PermissionResource($permission);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json(null, 204);
    }
}
