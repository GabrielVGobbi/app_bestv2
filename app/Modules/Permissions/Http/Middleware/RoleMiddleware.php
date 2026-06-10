<?php

namespace App\Modules\Permissions\Http\Middleware;

use App\Modules\Permissions\Models\Permission;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role, ?string $permission = null)
    {
        // Verifica autenticação
        if (! $request->user()) {
            return redirect()->route('login');
        }

        // Verifica role
        if (! $request->user()->hasRole($role)) {
            abort(403, 'Acesso negado');
        }

        // Verifica permission (se fornecida)
        if ($permission !== null) {
            $perm = Permission::where('slug', $permission)->first();

            if (! $perm || ! $request->user()->hasPermissionTo($perm)) {
                abort(403, 'Acesso negado');
            }
        }

        return $next($request);
    }
}
