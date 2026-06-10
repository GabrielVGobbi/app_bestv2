<?php

declare(strict_types=1);

namespace App\Modules\Clients\Providers;

use App\Modules\Clients\Models\Client;
use App\Modules\Clients\Observers\ClientObserver;
use App\Modules\Clients\Policies\ClientPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ClientsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Gate::policy(Client::class, ClientPolicy::class);
        Client::observe(ClientObserver::class);
    }
}
