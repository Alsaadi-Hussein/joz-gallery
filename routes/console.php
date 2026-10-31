<?php

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

Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();

Schedule::command('sitemap:generate')->daily();

Schedule::command('exports:prune')->daily();

// Cleaned first, so a full disk cannot stop tonight's backup.
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:30');
