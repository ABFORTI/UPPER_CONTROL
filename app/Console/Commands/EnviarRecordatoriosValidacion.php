<?php

namespace App\Console\Commands;

use App\Models\Orden;
use App\Models\User;
use App\Notifications\RecordatorioValidacionOt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnviarRecordatoriosValidacion extends Command
{
    protected $signature = 'recordatorios:validacion-ot';
    
    protected $description = 'Envía recordatorios a clientes con órdenes pendientes de validación';

    public function handle()
    {
        $this->info('🔍 Buscando órdenes pendientes de validación...');
        
        // Obtener el intervalo de recordatorios desde configuración (en minutos)
        $intervaloMinutos = config('business.recordatorio_validacion_intervalo_minutos', 360); // 6 horas por defecto
        
        // Buscar órdenes completadas, validadas por calidad, pero no autorizadas por cliente
        $ordenes = Orden::with(['solicitud.cliente', 'servicio', 'centro'])
            ->where('estatus', 'completada')
            ->where('calidad_resultado', 'validado')
            ->whereNull('cliente_autorizada_at')
            ->get();

        if ($ordenes->isEmpty()) {
            $this->info('✅ No hay órdenes pendientes de validación.');
            return 0;
        }

        $this->info("📋 Encontradas {$ordenes->count()} orden(es) pendiente(s).");
        
        $recordatoriosEnviados = 0;

        foreach ($ordenes as $orden) {
            // Obtener el cliente de la solicitud
            $cliente = $orden->solicitud?->cliente;
            
            if (!$cliente) {
                $this->warn("⚠️  OT #{$orden->id} no tiene cliente asociado.");
                continue;
            }

            // Verificar cuándo fue el último recordatorio
            $ultimoRecordatorio = DB::table('notifications')
                ->where('notifiable_id', $cliente->id)
                ->where('notifiable_type', User::class)
                ->where('type', 'App\Notifications\RecordatorioValidacionOt')
                ->where('data', 'like', '%"orden_id":' . $orden->id . '%')
                ->orderByDesc('created_at')
                ->first();

            // Si ya se envió un recordatorio recientemente, saltar
            if ($ultimoRecordatorio) {
                $fechaUltimo = \Carbon\Carbon::parse($ultimoRecordatorio->created_at);
                $minutosDesdeUltimo = $fechaUltimo->diffInMinutes(now());
                if ($minutosDesdeUltimo < $intervaloMinutos) {
                    $this->comment("⏳ OT #{$orden->id}: Último recordatorio hace {$minutosDesdeUltimo} min. Esperando...");
                    continue;
                }
            }

            // Calcular tiempo en espera desde que se validó por calidad
            // Buscar la fecha de validación de calidad
            $fechaValidacion = DB::table('activity_log')
                ->where('log_name', 'ordenes')
                ->where('subject_id', $orden->id)
                ->where('subject_type', Orden::class)
                ->where('event', 'calidad_validar')
                ->orderByDesc('created_at')
                ->value('created_at');

            $horasEspera = 0;
            if ($fechaValidacion) {
                $horasEspera = max(0, now()->diffInHours($fechaValidacion));
            } else {
                // Si no hay evento de validación, calcular desde updated_at de la orden
                $horasEspera = max(0, now()->diffInHours($orden->updated_at));
            }

            // Enviar recordatorio
            try {
                $cliente->notify(new RecordatorioValidacionOt($orden, $horasEspera));
                $recordatoriosEnviados++;
                $this->info("✅ Recordatorio enviado a {$cliente->email} para OT #{$orden->id} ({$horasEspera}h en espera)");
            } catch (\Exception $e) {
                $this->error("❌ Error al enviar recordatorio para OT #{$orden->id}: {$e->getMessage()}");
            }
        }

        $this->info("\n🎉 Proceso completado: {$recordatoriosEnviados} recordatorio(s) enviado(s).");
        
        return 0;
    }
}
