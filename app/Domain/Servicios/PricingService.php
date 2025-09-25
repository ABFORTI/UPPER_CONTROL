<?php

namespace App\Domain\Servicios;

use Illuminate\Support\Facades\DB;

class PricingService
{
    /**
     * Calcula el precio unitario según el centro, el servicio y opcionalmente el tamaño.
     * Estructura según migraciones actuales:
     * - servicios_centro(id, id_centrotrabajo, id_servicio, precio_base)
     * - servicio_tamanos(id, id_servicio_centro, tamano, precio)
     * Regla:
     * - Si existe precio por tamaño en servicio_tamanos para ese centro/servicio, úsalo.
     * - Si no existe o $tamano es null, usa precio_base de servicios_centro.
     */
    public function precioUnitario(int $centroId, int $servicioId, ?string $tamano = null): float
    {
        // 1) Encontrar el registro de servicios_centro para el centro y servicio
        $sc = DB::table('servicios_centro')
            ->where('id_centrotrabajo', $centroId)
            ->where('id_servicio', $servicioId)
            ->first();

        if (!$sc) {
            // No hay precio configurado para este centro/servicio
            return 0.0;
        }

        // 2) Si se indicó tamaño, intentar precio específico por tamaño
        if ($tamano) {
            $tamano = strtolower(trim($tamano));
            $st = DB::table('servicio_tamanos')
                ->where('id_servicio_centro', $sc->id)
                ->where('tamano', $tamano)
                ->first();
            if ($st && isset($st->precio)) {
                return (float)$st->precio;
            }
        }
        // 3) Fallback: precio base del servicio por centro (no "hereda" otros tamaños)
        return (float)($sc->precio_base ?? 0);
    }
}
