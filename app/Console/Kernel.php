<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Respaldar DB diario 02:30
        $schedule->command('backup:run --only-db')->dailyAt('02:30');

        // Respaldar archivos + DB semanal (domingo 02:45)
        $schedule->command('backup:run')->weeklyOn(0, '02:45');

        // Limpiar respaldos viejos domingo 03:10
        $schedule->command('backup:clean')->weeklyOn(0, '03:10');

        // Monitoreo diario 03:30
        $schedule->command('backup:monitor')->dailyAt('03:30');

        $schedule->command('activitylog:clean')->daily();
        // ...otros schedules...
    }

    // ...existing code...
}