<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Demo mode only (officer-desk.md section 8): keeps a hosted demo fresh for
// the next judge each morning. No effect unless RSL_DEMO=true and a
// scheduler is actually running (cron -> `php artisan schedule:run`).
if (config('rsl.demo')) {
    Schedule::command('rsl:demo --fresh --all')->dailyAt('03:00')->onOneServer();
}
