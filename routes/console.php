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

Schedule::command('telescope:prune --hours=48')->daily()->timezone('UTC')->withoutOverlapping()->onOneServer();
Schedule::command('exports:prune')->daily()->timezone('UTC')->withoutOverlapping()->onOneServer();
Schedule::command('uploads:prune-zips')->hourly()->timezone('UTC')->withoutOverlapping()->onOneServer();
Schedule::command('rich-text:prune-uploads --hours=24')->daily()->timezone('UTC')->withoutOverlapping()->onOneServer();
Schedule::command('database-exports:prune')->daily()->timezone('UTC')->withoutOverlapping()->onOneServer();

foreach (['backup:run' => '02:00', 'backup:clean' => '03:00', 'backup:monitor' => '04:00'] as $command => $time) {
    Schedule::command($command)->dailyAt($time)->timezone('UTC')->withoutOverlapping()->onOneServer()
        ->createMutexNameUsing('framework/schedule-backup-lifecycle');
}

Schedule::command('playlists:sync-spotify --limit=100 --stale-hours=24')->dailyAt('03:20')->timezone('UTC')->withoutOverlapping()->onOneServer();
Schedule::command('finance:recalculate-overdue --chunk=200')->dailyAt('00:10')->timezone('UTC')->withoutOverlapping()->onOneServer();
