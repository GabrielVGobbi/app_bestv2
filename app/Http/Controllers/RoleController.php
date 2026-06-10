<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('app/roles/index');
    }

    public function show(Role $role): Response
    {
        $role->load(['permissions', 'users']);

        return Inertia::render('app/roles/show', [
            'roleData' => new RoleResource($role),
        ]);
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('app/roles/edit', [
            'roleData' => new RoleResource($role),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.show', $role);
    }

    public function destroy(Role $role): RedirectResponse
    {
        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }

    public function permissions(Role $role): Response
    {
        $role->load('permissions');

        return Inertia::render('app/roles/permissions', [
            'roleData' => new RoleResource($role),
            'allPermissions' => PermissionResource::collection(Permission::orderBy('group')->orderBy('name')->get()),
        ]);
    }
}
