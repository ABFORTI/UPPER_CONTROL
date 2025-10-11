<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Orden;
use Carbon\Carbon;

class SimularOTVencida extends Command
{
    /**
     * Comando para simular una OT vencida (cambiando su updated_at) para probar el bloqueo.
     */
    protected $signature = 'dev:simular-ot-vencida {ordenId} {--minutos=2}';

    protected $description = 'Simula que una OT completada está vencida retrocediendo su updated_at';

    public function handle()
    {
        $ordenId = $this->argument('ordenId');
        $minutos = (int)$this->option('minutos');

        $orden = Orden::with('solicitud:id,folio')->find($ordenId);
        
        if (!$orden) {
            $this->error("No se encontró la orden #{$ordenId}");
            return 1;
        }

        $folio = $orden->solicitud ? $orden->solicitud->folio : "OT-{$orden->id}";

        if ($orden->estatus !== 'completada') {
            $this->warn("La orden {$folio} no está en estatus 'completada'. Estatus actual: {$orden->estatus}");
            $this->warn("Se cambiará el updated_at de todas formas...");
        }

        $nuevaFecha = Carbon::now()->subMinutes($minutos);
        
        // Actualizar sin disparar eventos ni cambiar updated_at automáticamente
        Orden::withoutTimestamps(function() use ($orden, $nuevaFecha) {
            $orden->update(['updated_at' => $nuevaFecha]);
        });

        $this->info("✓ Orden {$folio} (ID: {$orden->id})");
        $this->info("  Updated_at cambiado a: {$nuevaFecha->format('Y-m-d H:i:s')}");
        $this->info("  Hace {$minutos} minuto(s)");
        $this->line('');
        $this->info("Ahora intenta crear una solicitud como cliente del centro #{$orden->id_centrotrabajo}");
        $this->info("Deberías ver la pantalla de bloqueo si el timeout es menor a {$minutos} minutos.");
        
        return 0;
    }
}
