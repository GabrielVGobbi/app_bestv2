<?php

declare(strict_types=1);

namespace App\Modules\Companies\Providers;

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Observers\CompanyObserver;
use App\Modules\Companies\Policies\CompanyPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CompaniesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Gate::policy(Company::class, CompanyPolicy::class);
        Company::observe(CompanyObserver::class);
    }
}
