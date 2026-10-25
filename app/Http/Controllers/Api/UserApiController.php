<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteUsersRequest;
use App\Http\Requests\PaginationRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Supports\Api\ApiDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UserApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        $actor = $request->user();
        $canUpdate = Gate::allows('updateAny', User::class);
        $canDelete = Gate::allows('deleteAny', User::class);

        $query = User::query()
            ->select([
                'id',
                'uuid',
                'name',
                'email',
                'email_verified_at',
                'must_change_password',
                'created_at',
                'updated_at',
            ])
            ->when(
                $request->role(),
                fn (Builder $query, string $role): Builder => $query->withRole($role),
            )
            ->afterQuery(function (Collection $users) use ($actor, $canUpdate, $canDelete): Collection {
                return $users->each(function (Model $user) use ($actor, $canUpdate, $canDelete): void {
                    $user->setAttribute('can', [
                        'update' => $canUpdate && ! $user->is($actor),
                        'delete' => $canDelete && ! $user->is($actor) && $user->getKey() !== 1,
                    ]);
                });
            });

        return ApiDisplay::for(User::class)
            ->usingQuery($query)
            ->paginate($request);
    }

    public function syncRolesPermissions(Request $request, User $user): UserResource
    {
        Gate::authorize('update', $user);

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

    public function bulkDestroy(BulkDeleteUsersRequest $request): JsonResponse
    {
        $actor = $request->user();
        $ids = $request->validated('ids');

        $deletedIds = DB::transaction(function () use ($actor, $ids): array {
            $targets = User::query()->whereIn('uuid', $ids)->orderBy('id')->lockForUpdate()->get();

            if ($targets->count() !== count($ids)) {
                abort(404, 'User not found.');
            }

            foreach ($targets as $target) {
                Gate::forUser($actor)->authorize('delete', $target);
            }

            return $targets->each->delete()->map(fn (User $user): string => strtolower((string) $user->uuid))->values()->all();
        });

        return response()->json(['data' => ['deleted_ids' => $deletedIds, 'deleted_count' => count($deletedIds)]]);
    }
}
