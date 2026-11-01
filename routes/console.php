<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Shared hosting has no long-running workers, so one cron line runs
| schedule:run every minute and everything background starts from here.
|
*/

// Lets /api/health tell when cron has stopped, which nothing else on the site would show.
Schedule::call(fn () => Cache::forever(HealthController::SCHEDULER_KEY, time()))->everyMinute()->name('health-heartbeat');

// The lock lasts minutes, not the default day: hosts kill processes, and a dead
// worker's lock would otherwise silence order alerts until tomorrow.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping(2);

Schedule::command('sitemap:generate')->daily();

Schedule::command('exports:prune')->daily();

// Nothing else removes these, and shared hosting limits disk space and file counts.
Schedule::command('logs:trim')->hourly();
Schedule::command('cache:sweep')->daily();
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('queue:prune-batches --hours=720 --unfinished=720 --cancelled=720')->daily();

// Cleaned first, so a full disk cannot stop tonight's backup.
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:30');
