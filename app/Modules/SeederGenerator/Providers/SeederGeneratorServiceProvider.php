<?php

declare(strict_types=1);

namespace App\Modules\SeederGenerator\Providers;

use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class SeederGeneratorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        TrimStrings::skipWhen(fn (Request $request): bool => $request->is('admin/dev/seeder-generator', 'admin/dev/seeder-generator/*'));
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
    }
}
