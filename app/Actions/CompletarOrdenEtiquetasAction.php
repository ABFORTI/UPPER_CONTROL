<?php

// app/Actions/CompletarOrdenEtiquetasAction.php
namespace App\Actions;

use App\Models\Avance;
use App\Models\Orden;
use App\Models\OrdenItem;
use App\Models\OrdenItemProduccionSegmento;
use App\Models\OTServicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CompletarOrdenEtiquetasAction
{
    /**
     * Completar al 100% una OT proveniente del sistema de etiquetas Python.
     * Replica la lógica de OrdenController::registrarAvance() sin el contexto HTTP.
     *
     * @throws \RuntimeException si la OT no está disponible para completarse
     */
    public function execute(Orden $orden, User $operador): Orden
    {
        // ── Guardas de elegibilidad ──────────────────────────────────────────
        if (!($orden->solicitud?->es_integracion_etiquetas)) {
            throw new \RuntimeException("OT #{$orden->id}: no es una orden del sistema de etiquetas.");
        }

        if (in_array((string)($orden->ot_status ?? 'active'), ['partial', 'closed', 'canceled'], true)) {
            throw new \RuntimeException("OT #{$orden->id}: ot_status bloqueado ({$orden->ot_status}).");
        }

        if (in_array((string)$orden->estatus, ['completada', 'autorizada_cliente', 'facturada', 'entregada', 'cancelada'], true)) {
            throw new \RuntimeException("OT #{$orden->id}: ya en estatus final ({$orden->estatus}).");
        }

        if ($orden->factura()->exists() || $orden->facturas()->exists()) {
            throw new \RuntimeException("OT #{$orden->id}: ya tiene factura asociada.");
        }

        $esMultiServicio = $orden->otServicios()->exists();

        DB::transaction(function () use ($orden, $operador, $esMultiServicio) {

            if ($esMultiServicio) {
                $this->completarMultiServicio($orden);
            } else {
                $this->completarTradicional($orden, $operador);
            }

            // Marcar OT como completada
            $orden->estatus = 'completada';
            $orden->calidad_resultado = 'pendiente';
            if (Schema::hasColumn('ordenes_trabajo', 'fecha_completada')) {
                $orden->fecha_completada = now();
            }
            $orden->save();

            // Registrar avance histórico
            Avance::create([
                'id_orden'    => $orden->id,
                'id_item'     => null,
                'id_usuario'  => $operador->id,
                'user_id'     => $operador->id,
                'tipo'        => 'COMPLETADO_MASIVO',
                'cantidad'    => 0,
                'comentario'  => 'Completado al 100% masivamente (sistema etiquetas)',
                'es_corregido' => 0,
            ]);
        });

        // Notificar a usuarios de calidad del centro (fuera de la transacción)
        try {
            $usuariosCalidad = User::role('calidad')
                ->where(function ($q) use ($orden) {
                    $q->where('centro_trabajo_id', $orden->id_centrotrabajo)
                      ->orWhereHas('centros', function ($w) use ($orden) {
                          $w->where('centro_trabajo_id', $orden->id_centrotrabajo);
                      });
                })
                ->get();

            if ($usuariosCalidad->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send(
                    $usuariosCalidad,
                    new \App\Notifications\OtListaParaCalidad($orden)
                );
            }
        } catch (\Throwable $e) {
            Log::warning('CompletarOrdenEtiquetasAction: fallo al notificar calidad (ignorado)', [
                'orden_id' => $orden->id,
                'error'    => $e->getMessage(),
            ]);
        }

        // Activity log
        try {
            app(\Spatie\Activitylog\ActivityLogger::class)
                ->useLog('ordenes')
                ->performedOn($orden)
                ->causedBy($operador)
                ->event('completar_masivo_etiquetas')
                ->withProperties(['bulk' => true, 'operador_id' => $operador->id])
                ->log("OT #{$orden->id} completada al 100% masivamente (etiquetas)");
        } catch (\Throwable $e) {
            Log::warning('CompletarOrdenEtiquetasAction: fallo al registrar activity log (ignorado)', [
                'orden_id' => $orden->id,
                'error'    => $e->getMessage(),
            ]);
        }

        // PDF (fuera de transacción)
        \App\Jobs\GenerateOrdenPdf::dispatch($orden->id);

        return $orden;
    }

    // ── Helpers privados ─────────────────────────────────────────────────────

    private function completarMultiServicio(Orden $orden): void
    {
        $otServicios = OTServicio::where('ot_id', $orden->id)
            ->with('items.ajustes')
            ->lockForUpdate()
            ->get();

        foreach ($otServicios as $otServicio) {
            foreach ($otServicio->items as $item) {
                if ((int)$item->completado < (int)$item->planeado) {
                    $item->completado = (int)$item->planeado;
                    $item->save();
                }
            }
            // Recalcular subtotal del servicio desde las métricas (igual que registrarAvance)
            $otServicio->recalcularSubtotalDesdeCobrable();
        }

        // Recalcular totales de la OT desde la suma de servicios
        $subtotalOT = OTServicio::where('ot_id', $orden->id)->sum('subtotal');
        $ivaOT = round((float)$subtotalOT * 0.16, 2);
        $orden->subtotal    = $subtotalOT;
        $orden->iva         = $ivaOT;
        $orden->total       = (float)$subtotalOT + $ivaOT;
        $orden->total_real  = $subtotalOT;
        // estatus se fija en el llamador
    }

    private function completarTradicional(Orden $orden, User $operador): void
    {
        $items = OrdenItem::where('id_orden', $orden->id)
            ->with('ajustes')
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            $met       = $item->calcularMetricas();
            $objetivo  = (int)($met['total_cobrable'] ?? 0);
            $realActual = (int)($item->cantidad_real ?? 0);
            $remaining = max(0, $objetivo - $realActual);

            if ($remaining <= 0) {
                continue;
            }

            // Precio unitario: usar el del item, o inferir desde los subtotales de la OT
            $pu = (float)($item->precio_unitario ?? 0);
            if ($pu <= 0 && $objetivo > 0) {
                $sumObj = $this->sumTotalCobrableItems($items);
                $pu = $sumObj > 0
                    ? (float)($orden->subtotal ?? 0) / $sumObj
                    : 0.0;
            }
            if ($pu <= 0) {
                // Último fallback: imputar 0 (sin bloquear el flujo)
                $pu = 0.0;
            }

            $subSeg = round($pu * $remaining, 2);

            OrdenItemProduccionSegmento::create([
                'id_orden'       => $orden->id,
                'id_item'        => $item->id,
                'id_usuario'     => $operador->id,
                'tipo_tarifa'    => 'NORMAL',
                'cantidad'       => $remaining,
                'precio_unitario' => $pu,
                'subtotal'       => $subSeg,
                'nota'           => 'Completado masivo etiquetas',
            ]);

            // Actualizar agregados del item
            $qtyTotal = $realActual + $remaining;
            $subTotal = (float)OrdenItemProduccionSegmento::where('id_orden', $orden->id)
                ->where('id_item', $item->id)
                ->sum('subtotal');

            $item->cantidad_real  = $qtyTotal;
            $item->subtotal       = $subTotal;
            if ($qtyTotal > 0) {
                $item->precio_unitario = $subTotal / $qtyTotal;
            }
            $item->save();
        }

        // Recalcular totales monetarios desde la suma de subtotales de los ítems
        $totalReal = (float)OrdenItem::where('id_orden', $orden->id)
            ->selectRaw('COALESCE(SUM(subtotal),0) as t')
            ->value('t');

        $orden->total_real = $totalReal;
        $orden->subtotal   = $totalReal;
        $orden->iva        = round($totalReal * 0.16, 2);
        $orden->total      = $totalReal + $orden->iva;
        // estatus se fija en el llamador
    }

    /**
     * Sumar total cobrable de todos los ítems (para inferir precio unitario promedio).
     */
    private function sumTotalCobrableItems(\Illuminate\Support\Collection $items): int
    {
        return (int)$items->sum(function (OrdenItem $item) {
            $met = $item->calcularMetricas();
            return (int)($met['total_cobrable'] ?? 0);
        });
    }
}
