<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// One cron line on the host drives everything: `php artisan schedule:run` every minute.
// 15-minute mutex expiry: a killed worker never blocks the queue for 24 h.
Schedule::command('queue:work --stop-when-empty --max-time=840 --tries=1')
    ->everyMinute()->withoutOverlapping(15)->runInBackground();
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:30');
Schedule::command('backup:monitor')->dailyAt('03:00');
Schedule::command('notifications:prune --days=180')->dailyAt('02:30');
Schedule::command('queue:prune-failed --hours=720')->dailyAt('02:45');
Schedule::command('auth:clear-resets')->daily();
