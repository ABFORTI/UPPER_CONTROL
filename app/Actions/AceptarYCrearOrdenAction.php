<?php

namespace App\Actions;

use App\Jobs\GenerateOrdenPdf;
use App\Models\Orden;
use App\Models\OrdenItem;
use App\Models\OTServicio;
use App\Models\OTServicioItem;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\ActivityLogger;

/**
 * Aprueba una solicitud y crea automáticamente su orden de trabajo.
 * Reutilizada por el flujo masivo de coordinador. Para que sea atómica,
 * cada llamada a execute() envuelve su propia transacción.
 */
class AceptarYCrearOrdenAction
{
    /**
     * @throws \Throwable si algo falla dentro de la transacción
     */
    public function execute(Solicitud $solicitud, User $operador): Orden
    {
        return DB::transaction(function () use ($solicitud, $operador): Orden {

            // ── 1. Aprobar la solicitud (misma lógica que SolicitudController::aprobar) ──
            $solicitud->update([
                'estatus'      => 'aprobada',
                'aprobada_por' => $operador->id,
                'aprobada_at'  => now(),
            ]);

            $this->log('solicitudes')
                ->performedOn($solicitud)
                ->event('aprobar')
                ->causedBy($operador)
                ->withProperties(['resultado' => 'aprobada', 'bulk' => true])
                ->log("Solicitud {$solicitud->folio} aprobada (masivo coordinador) por {$operador->name}");

            // Notificar al cliente que su solicitud fue aprobada
            Notifier::toUser(
                $solicitud->id_cliente,
                'Solicitud aprobada',
                "Tu solicitud {$solicitud->folio} fue aprobada.",
                route('solicitudes.show', $solicitud->id)
            );

            // ── 2. Crear la OT (lógica adaptada de OrdenController) ──
            $solicitud->loadMissing(['servicio', 'tamanos', 'servicios.servicio', 'marca']);

            $esMultiServicio = $solicitud->servicios->isNotEmpty();

            $orden = Orden::create([
                'folio'               => $this->buildFolioOT($solicitud->id_centrotrabajo),
                'id_solicitud'        => $solicitud->id,
                'id_centrotrabajo'    => $solicitud->id_centrotrabajo,
                'id_servicio'         => $esMultiServicio ? null : $solicitud->id_servicio,
                'id_area'             => $solicitud->id_area,
                'team_leader_id'      => null,           // Sin asignación — queda en estado 'generada'
                'descripcion_general' => $solicitud->descripcion ?? '',
                'estatus'             => 'generada',
                'total_planeado'      => (int)($solicitud->cantidad ?? 0),
                'total_real'          => 0,
                'calidad_resultado'   => 'pendiente',
            ]);

            if ($esMultiServicio) {
                // ── Multi-servicio: copiar servicios y sus items ──
                foreach ($solicitud->servicios as $solServicio) {
                    $isPending = empty($solServicio->servicio_id)
                        || ($solServicio->service_assignment_status ?? null) === 'pending';

                    $otServ = OTServicio::create([
                        'ot_id'                      => $orden->id,
                        'servicio_id'                => $solServicio->servicio_id,
                        'tipo_cobro'                 => $solServicio->tipo_cobro,
                        'cantidad'                   => $solServicio->cantidad,
                        'precio_unitario'             => $isPending ? 0 : $solServicio->precio_unitario,
                        'subtotal'                   => $isPending ? 0 : $solServicio->subtotal,
                        'sku'                        => $solServicio->sku ?? null,
                        'origen_customs'             => $solServicio->origen ?? null,
                        'pedimento'                  => $solServicio->pedimento ?? null,
                        'marca'                      => $solicitud->marca?->nombre,
                        'service_assignment_status'  => $isPending ? 'pending' : 'assigned',
                        'service_locked'             => !$isPending,
                    ]);

                    OTServicioItem::create([
                        'ot_servicio_id'   => $otServ->id,
                        'descripcion_item' => $isPending
                            ? ($solicitud->descripcion ?? 'Pendiente de asignación de servicio')
                            : ($solServicio->servicio?->nombre ?? $solicitud->descripcion ?? 'Sin descripción'),
                        'planeado'         => $solServicio->cantidad,
                        'completado'       => 0,
                        'precio_unitario'  => $isPending ? 0 : $solServicio->precio_unitario,
                        'subtotal'         => $isPending ? 0 : $solServicio->subtotal,
                    ]);
                }

                $orden->recalcTotals();

            } else {
                // ── Tradicional: un solo item con la cantidad total de la solicitud ──
                $pricing   = app(\App\Domain\Servicios\PricingService::class);
                $usaTamanos = $solicitud->tamanos->isNotEmpty();

                // Si la solicitud tiene tamaños desglosados, crear un item por tamaño
                if ($usaTamanos) {
                    $sub = 0.0;
                    foreach ($solicitud->tamanos as $t) {
                        $tam  = (string)($t->tamano ?? '');
                        $cant = (int)($t->cantidad ?? 0);
                        if ($cant <= 0) continue;
                        $pu      = (float)$pricing->precioUnitario($solicitud->id_centrotrabajo, $solicitud->id_servicio, $tam);
                        $lineSub = $pu * $cant;
                        $sub    += $lineSub;
                        OrdenItem::create([
                            'id_orden'          => $orden->id,
                            'descripcion'       => ucfirst($tam),
                            'tamano'            => $tam,
                            'cantidad_planeada' => $cant,
                            'precio_unitario'   => $pu,
                            'subtotal'          => $lineSub,
                        ]);
                    }
                } else {
                    // Sin tamaños: item único
                    $pu  = (float)$pricing->precioUnitario($solicitud->id_centrotrabajo, $solicitud->id_servicio, null);
                    $cant = (int)($solicitud->cantidad ?? 0);
                    $sub  = $pu * $cant;
                    OrdenItem::create([
                        'id_orden'          => $orden->id,
                        'descripcion'       => $solicitud->descripcion ?? 'Sin descripción',
                        'tamano'            => null,
                        'cantidad_planeada' => $cant,
                        'precio_unitario'   => $pu,
                        'subtotal'          => $sub,
                    ]);
                }

                $ivaRate        = 0.16;
                $iva            = $sub * $ivaRate;
                $orden->subtotal = $sub;
                $orden->iva      = $iva;
                $orden->total    = $sub + $iva;
                $orden->save();
            }

            // ── 3. Activity log para la OT ──
            $this->log('ordenes')
                ->performedOn($orden)
                ->event('generar_ot_masivo')
                ->causedBy($operador)
                ->withProperties([
                    'solicitud_id'  => $solicitud->id,
                    'bulk'          => true,
                    'operador_id'   => $operador->id,
                    'operador_name' => $operador->name,
                ])
                ->log("OT #{$orden->id} generada masivamente desde solicitud {$solicitud->folio} por {$operador->name}");

            // ── 4. Notificaciones ──
            Notifier::toRoleInCentro(
                'calidad',
                $orden->id_centrotrabajo,
                'OT generada',
                "Se generó la OT #{$orden->id} (pendiente de revisión al completar).",
                route('ordenes.show', $orden->id)
            );

            // ── 5. Generar PDF en background ──
            GenerateOrdenPdf::dispatch($orden->id)->onQueue('pdfs');

            return $orden;
        });
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function buildFolioOT(int $centroId): string
    {
        $pref   = 'UPP';
        $yyyymm = now()->format('Ym');
        $seq    = Orden::where('id_centrotrabajo', $centroId)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count() + 1;

        return sprintf('%s-%s-%04d', $pref, $yyyymm, $seq);
    }

    private function log(string $channel): ActivityLogger
    {
        return app(ActivityLogger::class)->useLog($channel);
    }
}
