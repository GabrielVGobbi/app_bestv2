<?php

namespace App\Http\Middleware;

use App\Services\BrandingService;
use App\Services\NavigationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->withoutRelations(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Per request, not a once prop: Best Play filters the navigation by the user's roles and
            // permissions, so a client-kept copy would survive a login or logout in the same tab.
            'nav' => function (): array {
                $nav = app(NavigationService::class);

                return [
                    'style' => $nav->style(),
                    'modulesMenuPosition' => $nav->modulesMenuPosition(),
                    ...$nav->resolved(),
                ];
            },
        ];
    }

    /**
     * Config-derived props that only change on deploy; the client keeps them across visits
     * and a new asset version forces a full reload.
     *
     * @see https://inertiajs.com/docs/v3/data-props/once-props
     *
     * @return array<string, callable>
     */
    public function shareOnce(Request $request): array
    {
        return [
            ...parent::shareOnce($request),
            'name' => fn (): string => app(BrandingService::class)->name(),
            'branding' => fn (): array => app(BrandingService::class)->toArray(),
            'layout' => fn (): array => [
                'style' => config('layout.style'),
            ],
        ];
    }
}
