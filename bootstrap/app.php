<?php

use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Permissions\Http\Middleware\RoleMiddleware;
use App\Modules\Slack\Contracts\SlackServiceInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            // API pública/externa (3rd-party, mobile) — auth:sanctum (token ou sessão via statefulApi)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api/api.php'));

            // API interna consumida pela SPA React — mesmo guard, prefixo /api/web próprio
            Route::middleware('api')
                ->prefix('api/web')
                ->group(base_path('routes/api/web-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->statefulApi();

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            EnsurePasswordIsChanged::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $e, Request $request) {
            return Inertia::render('error', [
                'status' => $e->getStatusCode(),
            ])
                ->toResponse($request)
                ->setStatusCode($e->getStatusCode());
        });

        $exceptions->report(function (Throwable $e): void {
            // Skip expected / non-critical exceptions
            if ($e instanceof AuthenticationException) {
                return;
            }
            if ($e instanceof ValidationException) {
                return;
            }
            if ($e instanceof NotFoundHttpException) {
                return;
            }
            if ($e instanceof HttpException && $e->getStatusCode() < 500) {
                return;
            }

            rescue(fn () => app(SlackServiceInterface::class)->exception($e));
        });
    })->create();
