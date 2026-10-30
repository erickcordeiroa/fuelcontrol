<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('geocoding:purge-expired')->dailyAt('03:00');
Schedule::command('geocoding:resume-pending')->everyFifteenMinutes();

/**
 * Rede de segurança da fila: se o worker permanente (Supervisor) não estiver rodando, o agendador processa
 * a fila a cada minuto. Com o worker no ar, este quase nunca encontra trabalho e sai na hora.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();
