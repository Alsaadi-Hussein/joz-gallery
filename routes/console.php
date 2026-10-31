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
