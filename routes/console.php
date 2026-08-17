<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| 🔴 DEPLOY PREREQUISITE: nothing here runs without a `php artisan schedule:work`
| service on Railway — a THIRD service off the same image, alongside web and
| queue-worker. No domain and no healthcheck path (it listens on no port, so a
| healthcheck would crash-loop it). This is the first scheduled task in the
| project, so that service does not exist yet.
|
*/

// Warn staff before a Tamara authorisation lapses. Hourly, because the warning
// window is measured in hours and a daily run could miss it entirely.
// `withoutOverlapping` guards a run that outlives its slot; `onOneServer` is
// deliberately NOT used — it needs a shared lock store and there is exactly one
// scheduler container.
Schedule::command('payments:alert-expiring')
    ->hourly()
    ->withoutOverlapping();
