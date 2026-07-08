<?php

declare(strict_types=1);

namespace App\Modules\Finance\Providers;

use App\Modules\Finance\Listeners\CreateReceivableForConfirmedSale;
use App\Modules\Finance\Models\BankStatementImport;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialSettlement;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Models\ReconciliationMatch;
use App\Modules\Finance\Policies\FinancialAccountPolicy;
use App\Modules\Finance\Policies\FinancialCategoryPolicy;
use App\Modules\Finance\Policies\FinancialEntryPolicy;
use App\Modules\Finance\Policies\FinancialSettlementPolicy;
use App\Modules\Finance\Policies\PaymentMethodPolicy;
use App\Modules\Sales\Events\SaleConfirmed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::model('bank_statement_import', BankStatementImport::class);
        Route::model('financial_account', FinancialAccount::class);
        Route::model('financial_category', FinancialCategory::class);
        Route::model('financial_entry', FinancialEntry::class);
        Route::model('financial_installment', FinancialInstallment::class);
        Route::model('financial_settlement', FinancialSettlement::class);
        Route::model('match', ReconciliationMatch::class);
        Route::model('payment_method', PaymentMethod::class);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        Gate::policy(FinancialCategory::class, FinancialCategoryPolicy::class);
        Gate::policy(FinancialAccount::class, FinancialAccountPolicy::class);
        Gate::policy(FinancialEntry::class, FinancialEntryPolicy::class);
        Gate::policy(FinancialSettlement::class, FinancialSettlementPolicy::class);
        Gate::policy(PaymentMethod::class, PaymentMethodPolicy::class);

        Event::listen(SaleConfirmed::class, CreateReceivableForConfirmedSale::class);
    }
}
