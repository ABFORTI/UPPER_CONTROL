<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LimpiarRecordatorios extends Command
{
    protected $signature = 'recordatorios:limpiar';
    
    protected $description = 'Limpia todos los recordatorios de validación enviados';

    public function handle()
    {
        $this->info('🧹 Limpiando recordatorios anteriores...');
        
        $eliminados = DB::table('notifications')
            ->where('type', 'App\Notifications\RecordatorioValidacionOt')
            ->delete();
        
        $this->info("✅ {$eliminados} recordatorio(s) eliminado(s).");
        $this->info('Ahora puedes probar el envío de nuevo.');
        
        return 0;
    }
}
