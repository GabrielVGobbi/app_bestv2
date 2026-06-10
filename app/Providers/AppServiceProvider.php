<?php

namespace App\Providers;

use App\Listeners\LogUserLogin;
use App\Listeners\LogUserLogout;
use App\Modules\Slack\Contracts\SlackServiceInterface;
use App\Modules\Slack\Services\SlackService;
use App\Services\NavigationService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NavigationService::class);
        $this->app->singleton(SlackServiceInterface::class, SlackService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        $this->registerObservers();

        $this->registerLocale();

        $this->registerListeners();

    }

    private function registerObservers(): void {}

    private function registerListeners(): void
    {
        Event::listen(Login::class, LogUserLogin::class);
        Event::listen(Logout::class, LogUserLogout::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): Password {
            $rule = Password::min(6);

            return app()->isProduction()
                ? $rule
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : $rule;
        });

        Model::preventLazyLoading(! app()->isProduction());
    }

    private function registerLocale(): void
    {
        setlocale(LC_ALL, 'nl_NL');
        Carbon::setLocale('pt_BR');
        date_default_timezone_set('America/Sao_Paulo');
    }
}
