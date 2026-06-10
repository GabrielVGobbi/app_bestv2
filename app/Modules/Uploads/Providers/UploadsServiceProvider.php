<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Providers;

use App\Modules\Uploads\Http\Controllers\UploadController;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Policies\UploadedPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UploadsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/uploads.php', 'uploads');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Gate::policy(Uploaded::class, UploadedPolicy::class);

        Route::middleware((array) config('uploads.route_middleware', ['auth']))
            ->prefix((string) config('uploads.route_prefix', 'api/v1/uploads'))
            ->name('api.uploads.')
            ->group(__DIR__.'/../Routes/api.php');

        $webPrefix = (string) config('uploads.web_route_prefix', 'uploads');
        $webMiddleware = (array) config('uploads.web_route_middleware', ['web', 'auth']);

        // Protected routes (download, etc.)
        Route::middleware($webMiddleware)
            ->prefix($webPrefix)
            ->name('uploads.')
            ->group(__DIR__.'/../Routes/web.php');

        // Preview — no auth middleware; controller checks public_rules or falls back to Gate
        Route::middleware('web')
            ->prefix($webPrefix)
            ->get('/{upload}/preview', [UploadController::class, 'preview'])
            ->name('uploads.preview');
    }
}
