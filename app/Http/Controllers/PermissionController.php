<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Modules\Permissions\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('app/permissions/index');
    }

    public function show(Permission $permission): Response
    {
        return Inertia::render('app/permissions/show', [
            'permissionData' => new PermissionResource($permission),
        ]);
    }

    public function edit(Permission $permission): Response
    {
        return Inertia::render('app/permissions/edit', [
            'permissionData' => new PermissionResource($permission),
        ]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $permission->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission updated.')]);

        return to_route('permissions.show', $permission);
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission deleted.')]);

        return to_route('permissions.index');
    }
}
