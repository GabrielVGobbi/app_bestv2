<?php

declare(strict_types=1);

namespace App\Modules\Finance\Providers;

use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Policies\FinancialCategoryPolicy;
use App\Modules\Finance\Policies\PaymentMethodPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::model('financial_category', FinancialCategory::class);
        Route::model('payment_method', PaymentMethod::class);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        Gate::policy(FinancialCategory::class, FinancialCategoryPolicy::class);
        Gate::policy(PaymentMethod::class, PaymentMethodPolicy::class);
    }
}
