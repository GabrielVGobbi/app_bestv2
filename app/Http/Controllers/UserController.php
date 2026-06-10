<?php

namespace App\Http\Controllers;

use App\Actions\Users\CreateUserWithTemporaryPassword;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use App\Supports\TheOneResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('app/users/index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('app/users/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request, CreateUserWithTemporaryPassword $createUser): RedirectResponse
    {
        ['user' => $user, 'temporary_password' => $temporaryPassword] = $createUser->handle($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created. Copy the temporary password and send it to the user.')]);
        Inertia::flash('temporaryUserPassword', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $temporaryPassword,
        ]);

        if ($request->boolean('redirect_to_index')) {
            return to_route('users.index');
        }

        return to_route('users.show', $user);
    }

    /**
     * Display the specified resource.
     */
    public function show($userUuid): Response|TheOneResponse
    {
        if (! $user = User::whereUuid($userUuid)->first()) {
            return TheOneResponse::notFound('User not found', 'users.index');
        }

        $user->load(['roles', 'permissions']);

        return Inertia::render('app/users/show', [
            'userData' => new UserResource($user),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): Response
    {
        return Inertia::render('app/users/edit', [
            'userData' => new UserResource($user),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.show', $user);
    }

    public function roles($userUuid): Response|TheOneResponse
    {
        if (! $user = User::whereUuid($userUuid)->first()) {
            return TheOneResponse::notFound('User not found', 'users.index');
        }

        $user->load(['roles', 'permissions']);

        return Inertia::render('app/users/roles', [
            'userData' => new UserResource($user),
            'allRoles' => RoleResource::collection(Role::orderBy('name')->get()),
        ]);
    }

    public function userPermissions($userUuid): Response|TheOneResponse
    {
        if (! $user = User::whereUuid($userUuid)->first()) {
            return TheOneResponse::notFound('User not found', 'users.index');
        }

        $user->load(['roles', 'permissions']);

        return Inertia::render('app/users/permissions', [
            'userData' => new UserResource($user),
            'allPermissions' => PermissionResource::collection(Permission::orderBy('group')->orderBy('name')->get()),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === 1) {
            return to_route('users.index');
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('users.index');
    }
}
