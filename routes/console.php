<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('geocoding:purge-expired')->dailyAt('03:00');
Schedule::command('geocoding:resume-pending')->dailyAt('00:10');
