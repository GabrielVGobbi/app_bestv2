<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:clear', function () {
    $this->call('cache:clear');
    $this->call('config:clear');
    $this->call('route:clear');
    $this->call('event:clear');
    $this->call('view:clear');
    // $this->call('wayfinder:generate', ['--with-form' => 'true']);
    $this->call('telescope:prune', ['--hours' => 48]);
    $this->info('Todos os caches foram limpos com sucesso!');
})->purpose('Clear application caches');

Schedule::command('telescope:prune --hours=48')->daily();
Schedule::command('exports:prune')->daily();
Schedule::command('rich-text:prune-uploads --hours=24')->daily();
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
Schedule::command('database-exports:prune')->daily();
Schedule::command('playlists:sync-spotify --limit=100 --stale-hours=24')->dailyAt('03:20')->withoutOverlapping()->onOneServer();
