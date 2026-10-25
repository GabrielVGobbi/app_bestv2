<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()?->must_change_password
            && ! $request->routeIs('password.force.*', 'user-password.update', 'logout', 'api.auth.logout', 'api.auth.logout-all', 'verification.notice', 'verification.send', 'verification.verify')
        ) {
            if ($request->is('api/*')) {
                $message = __('You must change your password before continuing.');

                return response()->json([
                    'message' => $message,
                    'errors' => ['password' => [$message]],
                ], Response::HTTP_FORBIDDEN);
            }

            return redirect()->route('password.force.edit');
        }

        return $next($request);
    }
}
