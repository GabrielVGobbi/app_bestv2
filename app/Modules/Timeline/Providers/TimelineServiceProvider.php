<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Providers;

use App\Modules\Timeline\Models\TimelineComment;
use App\Modules\Timeline\Policies\TimelineCommentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TimelineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/timeline.php', 'timeline');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Gate::policy(TimelineComment::class, TimelineCommentPolicy::class);

        Route::middleware((array) config('timeline.route_middleware', ['auth:sanctum']))
            ->prefix((string) config('timeline.route_prefix', 'api/v1/timeline'))
            ->name('api.timeline.')
            ->group(__DIR__.'/../Routes/api.php');
    }
}
