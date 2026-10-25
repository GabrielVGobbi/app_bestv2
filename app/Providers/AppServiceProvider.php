<?php

namespace App\Providers;

use App\Listeners\LogUserLogin;
use App\Listeners\LogUserLogout;
use App\Models\User;
use App\Modules\Slack\Contracts\SlackServiceInterface;
use App\Modules\Slack\Services\SlackService;
use App\Policies\UserPolicy;
use App\Services\NavigationService;
use App\Supports\WindowsViewFilesystem;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\TransientToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->app->extend('files', fn () => new WindowsViewFilesystem($this->app['config']['view.compiled']));
        }

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

        $this->registerRateLimiters();

        $this->registerGates();
    }

    private function registerObservers(): void {}

    /**
     * Integration settings, e-mail logs and developer tools stay session-only: Sanctum bearer
     * tokens never reach them. Roles come from the Best Play ACL (developer bypasses via super_role).
     */
    private function registerGates(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        $manageIntegrationSettings = function (User $user): bool {
            return config('permissions.super_role') === 'developer'
                && $user->hasRole('developer')
                && request()->bearerToken() === null
                && $this->sessionOnlyToken($user->currentAccessToken());
        };

        foreach (['manageIntegrationSettings', 'view-email-logs', 'resend-email-logs', 'manage-email-settings'] as $ability) {
            Gate::define($ability, $manageIntegrationSettings);
        }

        Gate::define('useDeveloperTools', function (User $user): bool {
            $environment = app()->environment();

            return in_array($environment, ['local', 'testing'], true)
                && config('app.env') === $environment
                && config('permissions.super_role') === 'developer'
                && $user->hasRole('developer')
                && request()->bearerToken() === null
                && $this->sessionOnlyToken($user->currentAccessToken());
        });

        Gate::define('viewPulse', fn (User $user): bool => Gate::forUser($user)->allows('useDeveloperTools'));
    }

    private function sessionOnlyToken(mixed $token): bool
    {
        return $token === null || $token instanceof TransientToken;
    }

    private function registerListeners(): void
    {
        Event::listen(Login::class, LogUserLogin::class);
        Event::listen(Logout::class, LogUserLogout::class);
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('spotify-playlist-sync', fn (): Limit => Limit::perMinute(20)->by('spotify-playlist-sync'));
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
