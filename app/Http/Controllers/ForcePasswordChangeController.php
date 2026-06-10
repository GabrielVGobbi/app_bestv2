<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ForcePasswordChangeController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('auth/force-password-change');
    }

    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->password,
            'must_change_password' => false,
        ]);

        // Sanctum's AuthenticateSession middleware stores password_hash_web in the
        // session and compares it on every stateful API call. After a password change
        // the DB hash changes but the session still holds the old value → 401.
        // We update the session key here using the same format the middleware uses.
        $guard = auth()->guard('web');
        $request->session()->put(
            'password_hash_web',
            $guard instanceof SessionGuard && method_exists($guard, 'hashPasswordForCookie')
                ? $guard->hashPasswordForCookie($user->getAuthPassword())
                : $user->getAuthPassword()
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return to_route('app.dashboard');
    }
}
