<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Tareas que sostienen el estado del sistema: el barrido de vencimientos y la
 * mora diaria, los recordatorios, la copia de seguridad y el recálculo de
 * scoring. Sin esta agenda ningún comando corre solo, así que requiere que
 * `php artisan schedule:run` se ejecute cada minuto (cron en producción).
 */
Schedule::command('loans:mark-overdue')
    ->dailyAt('00:10')
    ->withoutOverlapping();

Schedule::command('loans:reminders')
    ->dailyAt('00:30')
    ->withoutOverlapping();

Schedule::command('backup:database')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('clients:recalc-score')
    ->weekly()
    ->withoutOverlapping();
