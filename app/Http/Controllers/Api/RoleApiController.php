<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Modules\Permissions\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        $query = Role::query()->withCount(['permissions', 'users']);

        $search = $request->searchTerm();
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $sort = $request->sortColumn() ?? 'name';
        $direction = $request->sortDirection() ?? 'asc';
        $query->orderBy($sort, $direction);

        return RoleResource::collection(
            $query->paginate($request->perPage())->withQueryString()
        );
    }

    public function store(StoreRoleRequest $request): RoleResource
    {
        $role = Role::create($request->validated());

        return new RoleResource($role);
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $role->update($request->validated());

        return new RoleResource($role);
    }

    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        return response()->json(null, 204);
    }

    public function syncPermissions(Request $request, Role $role): RoleResource
    {
        $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($request->input('permissions', []));

        $role->load('permissions');

        return new RoleResource($role);
    }
}
