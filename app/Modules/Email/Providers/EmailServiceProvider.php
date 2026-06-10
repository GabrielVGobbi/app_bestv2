<?php

declare(strict_types=1);

namespace App\Modules\Email\Providers;

use App\Modules\Email\Contracts\EmailServiceInterface;
use App\Modules\Email\Services\EmailService;
use App\Modules\Email\Services\EmailWebhookService;
use Illuminate\Support\ServiceProvider;

class EmailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmailServiceInterface::class, EmailService::class);
        $this->app->singleton(EmailWebhookService::class);
    }

    public function boot(): void
    {
        //
    }
}
