<?php

namespace App\Console\Commands;

use App\Models\Orden;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimularOtPendiente extends Command
{
    protected $signature = 'test:simular-ot-pendiente {orden_id}';
    
    protected $description = 'Simula una OT pendiente de validación para testing de recordatorios';

    public function handle()
    {
        $ordenId = $this->argument('orden_id');
        
        $orden = Orden::find($ordenId);
        
        if (!$orden) {
            $this->error("❌ OT #{$ordenId} no encontrada.");
            return 1;
        }

        $this->info("🔧 Configurando OT #{$ordenId} como pendiente de validación...");

        // Actualizar la orden
        $orden->estatus = 'completada';
        $orden->calidad_resultado = 'validado';
        $orden->cliente_autorizada_at = null;
        $orden->save();

        // Registrar evento de validación de calidad (si no existe)
        $existeEvento = DB::table('activity_log')
            ->where('log_name', 'ordenes')
            ->where('subject_id', $orden->id)
            ->where('subject_type', Orden::class)
            ->where('event', 'calidad_validar')
            ->exists();

        if (!$existeEvento) {
            // Crear el evento de validación hace 1 hora (para simular)
            DB::table('activity_log')->insert([
                'log_name' => 'ordenes',
                'description' => "OT #{$orden->id} validada por calidad (simulación)",
                'subject_type' => Orden::class,
                'subject_id' => $orden->id,
                'event' => 'calidad_validar',
                'causer_type' => null,
                'causer_id' => null,
                'properties' => json_encode(['test' => true]),
                'created_at' => now()->subHour(),
                'updated_at' => now()->subHour(),
            ]);
            $this->info("✅ Evento de validación creado (simulado hace 1 hora)");
        }

        // Limpiar recordatorios anteriores para esta orden
        DB::table('notifications')
            ->where('type', 'App\Notifications\RecordatorioValidacionOt')
            ->whereJsonContains('data->orden_id', (string)$ordenId)
            ->delete();

        $this->info("✅ Recordatorios anteriores eliminados");

        $this->newLine();
        $this->info("✅ OT #{$ordenId} configurada correctamente");
        $this->info("📋 Estado:");
        $this->info("   - Estatus: {$orden->estatus}");
        $this->info("   - Calidad: {$orden->calidad_resultado}");
        $this->info("   - Cliente autorizada: " . ($orden->cliente_autorizada_at ? 'SÍ' : 'NO'));
        $this->newLine();
        $this->info("🧪 Ahora puedes probar:");
        $this->comment("   php artisan recordatorios:validacion-ot");

        return 0;
    }
}
