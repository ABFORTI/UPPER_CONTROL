<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios de validación de OT para clientes
Schedule::command('recordatorios:validacion-ot')->everyMinute();

// Test scheduler
Schedule::command('test:schedule')->everyMinute();
