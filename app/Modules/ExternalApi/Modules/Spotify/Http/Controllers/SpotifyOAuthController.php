<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ExternalApi\Modules\Spotify\Services\SpotifyManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpotifyOAuthController extends Controller
{
    public function __construct(private SpotifyManager $spotify) {}

    public function redirect(Request $request): RedirectResponse
    {
        return redirect()->away(
            $this->spotify->auth()->getAuthorizationUrl($request->user())
        );
    }

    public function callback(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $this->spotify->auth()->handleCallback(
            $request->user(),
            $request->string('code')->toString(),
            $request->string('state')->toString()
        );

        return redirect()
            ->route('app.dashboard')
            ->with('success', 'Spotify conectado com sucesso.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->spotify->auth()->disconnect($request->user());

        return redirect()
            ->route('app.dashboard')
            ->with('success', 'Spotify desconectado com sucesso.');
    }
}
