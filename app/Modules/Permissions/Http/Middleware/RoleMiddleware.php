<?php

namespace App\Modules\Permissions\Http\Middleware;

use App\Modules\Permissions\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $roles, ?string $permission = null): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $acceptedRoles = preg_split('/[|,]/', $roles, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        if (! $request->user()->hasRole(...$acceptedRoles)) {
            abort(403, 'Acesso negado');
        }

        if ($permission !== null) {
            $perm = Permission::where('slug', $permission)->first();

            if (! $perm || ! $request->user()->hasPermissionTo($perm)) {
                abort(403, 'Acesso negado');
            }
        }

        return $next($request);
    }
}
