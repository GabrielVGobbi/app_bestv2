<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Supports\Api\ApiDisplay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        return ApiDisplay::for(User::class)
            ->usingQuery(User::query()->select([
                'id',
                'uuid',
                'name',
                'email',
                'email_verified_at',
                'must_change_password',
                'created_at',
                'updated_at',
            ]))
            ->paginate($request);
    }

    public function syncRolesPermissions(Request $request, User $user): UserResource
    {
        $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $user->roles()->sync($request->input('roles', []));
        $user->permissions()->sync($request->input('permissions', []));

        $user->load(['roles', 'permissions']);

        return new UserResource($user);
    }
}
