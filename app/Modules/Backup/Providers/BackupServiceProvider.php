<?php

declare(strict_types=1);

namespace App\Modules\Backup\Providers;

use App\Modules\Backup\Console\Commands\PruneDatabaseExports;
use App\Modules\Backup\Listeners\SyncBackupToHistory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;

class BackupServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->commands([PruneDatabaseExports::class]);

        $listener = new SyncBackupToHistory;

        Event::listen(BackupWasSuccessful::class, [$listener, 'handleSuccess']);
        Event::listen(BackupHasFailed::class, [$listener, 'handleFailure']);
    }
}
