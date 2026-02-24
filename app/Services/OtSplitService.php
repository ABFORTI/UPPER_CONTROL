<?php

namespace App\Services;

use App\Models\Avance;
use App\Models\Orden;
use App\Models\OrdenItem;
use App\Models\OtCorte;
use App\Models\OtCorteDetalle;
use App\Models\OTServicio;
use App\Models\OTServicioItem;
use App\Notifications\OtCorteGeneradoNotification;
use App\Notifications\OtHijaCreadaNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OtSplitService
{
    // ─────────────────────────────────────────────────────────
    // Detección de flujo
    // ─────────────────────────────────────────────────────────

    protected function esMultiServicio(Orden $ot): bool
    {
        return $ot->otServicios()->exists();
    }

    // ─────────────────────────────────────────────────────────
    // Preview
    // ─────────────────────────────────────────────────────────

    /**
     * Genera la preview de un corte, soporte para ambos flujos (multi-servicio y tradicional).
     */
    public function preview(Orden $ot, ?string $periodoInicio = null, ?string $periodoFin = null): array
    {
        if ($this->esMultiServicio($ot)) {
            return $this->previewMultiServicio($ot, $periodoInicio, $periodoFin);
        }

        return $this->previewTradicional($ot, $periodoInicio, $periodoFin);
    }

    protected function previewMultiServicio(Orden $ot, ?string $periodoInicio, ?string $periodoFin): array
    {
        $ot->load(['otServicios.servicio', 'otServicios.avances']);

        $conceptos = [];

        foreach ($ot->otServicios as $srv) {
            $contratado = (float) $srv->cantidad;
            $precioUnit = (float) $srv->precio_unitario;

            $ejecutadoTotal = (float) $srv->avances->sum('cantidad_registrada');

            $cortadoPrevio = (float) OtCorteDetalle::where('ot_servicio_id', $srv->id)
                ->whereHas('corte', fn ($q) => $q->where('ot_id', $ot->id)->where('estatus', '!=', 'void'))
                ->sum('cantidad_cortada');

            $ejecutadoNoCortado = max(0, $ejecutadoTotal - $cortadoPrevio);

            $ejecutadoEnPeriodo = $ejecutadoTotal;
            if ($periodoInicio && $periodoFin) {
                $ejecutadoEnPeriodo = (float) $srv->avances()
                    ->whereBetween('created_at', [$periodoInicio . ' 00:00:00', $periodoFin . ' 23:59:59'])
                    ->sum('cantidad_registrada');
            }

            $sugerencia = min($ejecutadoEnPeriodo, $ejecutadoNoCortado, $contratado);

            $srvNombre = $srv->servicio->nombre ?? 'Servicio #' . $srv->servicio_id;

            $conceptos[] = [
                'concepto_tipo'             => 'servicio',
                'ot_servicio_id'            => $srv->id,
                'orden_item_id'             => null,
                'servicio_nombre'           => $srvNombre,
                'tipo_cobro'                => $srv->tipo_cobro,
                'contratado'                => $contratado,
                'ejecutado_total'           => $ejecutadoTotal,
                'cortado_previo'            => $cortadoPrevio,
                'ejecutado_no_cortado'      => $ejecutadoNoCortado,
                'ejecutado_en_periodo'      => $ejecutadoEnPeriodo,
                'sugerencia_cantidad_corte' => $sugerencia,
                'precio_unitario'           => $precioUnit,
                'importe_sugerido'          => round($sugerencia * $precioUnit, 2),
            ];
        }

        return $conceptos;
    }

    protected function previewTradicional(Orden $ot, ?string $periodoInicio, ?string $periodoFin): array
    {
        $ot->load(['items']);

        $conceptos = [];

        foreach ($ot->items as $item) {
            $contratado = (float) $item->cantidad_planeada;
            $precioUnit = (float) ($item->precio_unitario ?? 0);

            $ejecutadoTotal = (float) Avance::where('id_item', $item->id)->sum('cantidad');

            $cortadoPrevio = (float) OtCorteDetalle::where('orden_item_id', $item->id)
                ->whereHas('corte', fn ($q) => $q->where('ot_id', $ot->id)->where('estatus', '!=', 'void'))
                ->sum('cantidad_cortada');

            $ejecutadoNoCortado = max(0, $ejecutadoTotal - $cortadoPrevio);

            $ejecutadoEnPeriodo = $ejecutadoTotal;
            if ($periodoInicio && $periodoFin) {
                $ejecutadoEnPeriodo = (float) Avance::where('id_item', $item->id)
                    ->whereBetween('created_at', [$periodoInicio . ' 00:00:00', $periodoFin . ' 23:59:59'])
                    ->sum('cantidad');
            }

            $sugerencia = min($ejecutadoEnPeriodo, $ejecutadoNoCortado, $contratado);

            $itemNombre = $item->descripcion ?? 'Item #' . $item->id;

            $conceptos[] = [
                'concepto_tipo'             => 'item',
                'ot_servicio_id'            => null,
                'orden_item_id'             => $item->id,
                'servicio_nombre'           => $itemNombre,
                'tipo_cobro'                => 'unitario',
                'contratado'                => $contratado,
                'ejecutado_total'           => $ejecutadoTotal,
                'cortado_previo'            => $cortadoPrevio,
                'ejecutado_no_cortado'      => $ejecutadoNoCortado,
                'ejecutado_en_periodo'      => $ejecutadoEnPeriodo,
                'sugerencia_cantidad_corte' => $sugerencia,
                'precio_unitario'           => $precioUnit,
                'importe_sugerido'          => round($sugerencia * $precioUnit, 2),
            ];
        }

        return $conceptos;
    }

    // ─────────────────────────────────────────────────────────
    // Crear corte
    // ─────────────────────────────────────────────────────────

    /**
     * Crea un corte y opcionalmente la OT hija con remanente.
     * Soporta flujo multi-servicio (ot_servicio_id) y tradicional (orden_item_id).
     */
    public function crearCorte(Orden $ot, array $data, int $userId): OtCorte
    {
        $corte = DB::transaction(function () use ($ot, $data, $userId) {
            $esMulti     = $this->esMultiServicio($ot);
            $crearOtHija = $data['crear_ot_hija'] ?? true;

            if ($esMulti) {
                $ot->load(['otServicios.servicio']);
                [$detallesCrear, $montoTotal] = $this->validarDetallesMultiServicio($ot, $data['detalles']);
            } else {
                $ot->load(['items']);
                [$detallesCrear, $montoTotal] = $this->validarDetallesTradicional($ot, $data['detalles']);
            }

            if (empty($detallesCrear)) {
                throw new \InvalidArgumentException('Debe incluir al menos un concepto con cantidad > 0.');
            }

            // Crear el corte
            $corte = OtCorte::create([
                'ot_id'          => $ot->id,
                'periodo_inicio' => $data['periodo_inicio'],
                'periodo_fin'    => $data['periodo_fin'],
                'folio_corte'    => OtCorte::generarFolio($ot->id),
                'estatus'        => 'ready_to_bill',
                'monto_total'    => round($montoTotal, 2),
                'created_by'     => $userId,
            ]);

            foreach ($detallesCrear as $det) {
                $corte->detalles()->create($det);
            }

            // Crear OT hija si se requiere
            $hayRemanente = false;
            if ($crearOtHija) {
                if ($esMulti) {
                    $otHija = $this->crearOtHijaMultiServicio($ot);
                } else {
                    $otHija = $this->crearOtHijaTradicional($ot);
                }

                if ($otHija) {
                    $corte->update(['ot_hija_id' => $otHija->id]);
                    $hayRemanente = true;
                }
            }

            $this->actualizarEstatusOt($ot, $hayRemanente);

            return $corte->load('detalles', 'otHija');
        });

        // ── Notificaciones post-transacción ──
        $this->notificarCorteGenerado($ot, $corte);

        return $corte;
    }

    /**
     * Dispara notificaciones cuando se genera un corte y/o OT hija.
     */
    protected function notificarCorteGenerado(Orden $ot, OtCorte $corte): void
    {
        try {
            $centroId = $ot->id_centrotrabajo;

            // Notificar a coordinador y facturación sobre el corte
            Notifier::toRoleInCentro('coordinador', $centroId,
                'Corte de OT Generado',
                "Corte {$corte->folio_corte} generado para OT #{$ot->id} por \${$corte->monto_total}.",
                route('ordenes.show', $ot->id)
            );
            Notifier::toRoleInCentro('facturacion', $centroId,
                'Corte Listo para Facturar',
                "Corte {$corte->folio_corte} de OT #{$ot->id} está listo para facturación (\${$corte->monto_total}).",
                route('ordenes.show', $ot->id)
            );

            // Si se creó OT hija, notificar al TL asignado y coordinador
            if ($corte->otHija) {
                $otHija = $corte->otHija;

                Notifier::toRoleInCentro('coordinador', $centroId,
                    'OT Hija Creada',
                    "OT Hija #{$otHija->id} creada a partir de OT #{$ot->id}.",
                    route('ordenes.show', $otHija->id)
                );

                // Notificar al TL si existe
                if ($otHija->id_team_leader) {
                    Notifier::toUser($otHija->id_team_leader,
                        'OT Hija Asignada',
                        "Se te asignó la OT Hija #{$otHija->id} (remanente de OT #{$ot->id}).",
                        route('ordenes.show', $otHija->id)
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OtSplitService: fallo al enviar notificaciones de corte (ignorado)', [
                'ot_id'  => $ot->id,
                'corte_id' => $corte->id,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────
    // Validaciones internas
    // ─────────────────────────────────────────────────────────

    protected function validarDetallesMultiServicio(Orden $ot, array $detallesInput): array
    {
        $montoTotal   = 0;
        $detallesCrear = [];

        foreach ($detallesInput as $det) {
            if (empty($det['ot_servicio_id'])) {
                continue;
            }

            $srv = $ot->otServicios->firstWhere('id', $det['ot_servicio_id']);
            if (! $srv) {
                throw new \InvalidArgumentException(
                    "El servicio OT #{$det['ot_servicio_id']} no pertenece a esta OT."
                );
            }

            $cantidadCortada = (float) $det['cantidad_cortada'];
            if ($cantidadCortada <= 0) {
                continue;
            }

            $ejecutadoTotal = (float) $srv->avances()->sum('cantidad_registrada');

            $cortadoPrevio = (float) OtCorteDetalle::where('ot_servicio_id', $srv->id)
                ->whereHas('corte', fn ($q) => $q->where('ot_id', $ot->id)->where('estatus', '!=', 'void'))
                ->sum('cantidad_cortada');

            $ejecutadoNoCortado = $ejecutadoTotal - $cortadoPrevio;
            $srvNombre = $srv->servicio->nombre ?? 'Servicio #' . $srv->id;

            if ($cantidadCortada > $ejecutadoNoCortado) {
                throw new \InvalidArgumentException(
                    "Servicio \"{$srvNombre}\": cantidad a cortar ({$cantidadCortada}) " .
                    "excede el ejecutado no cortado ({$ejecutadoNoCortado})."
                );
            }
            if ($cantidadCortada > (float) $srv->cantidad) {
                throw new \InvalidArgumentException(
                    "Servicio \"{$srvNombre}\": cantidad a cortar ({$cantidadCortada}) " .
                    "excede lo contratado ({$srv->cantidad})."
                );
            }

            $precioUnit = (float) $srv->precio_unitario;
            $importe    = round($cantidadCortada * $precioUnit, 2);
            $montoTotal += $importe;

            $detallesCrear[] = [
                'ot_servicio_id'           => $srv->id,
                'orden_item_id'            => null,
                'concepto_descripcion'     => $srvNombre,
                'cantidad_cortada'         => $cantidadCortada,
                'precio_unitario_snapshot' => $precioUnit,
                'importe_snapshot'         => $importe,
            ];
        }

        return [$detallesCrear, $montoTotal];
    }

    protected function validarDetallesTradicional(Orden $ot, array $detallesInput): array
    {
        $montoTotal    = 0;
        $detallesCrear = [];

        foreach ($detallesInput as $det) {
            if (empty($det['orden_item_id'])) {
                continue;
            }

            $item = $ot->items->firstWhere('id', $det['orden_item_id']);
            if (! $item) {
                throw new \InvalidArgumentException(
                    "El item #{$det['orden_item_id']} no pertenece a esta OT."
                );
            }

            $cantidadCortada = (float) $det['cantidad_cortada'];
            if ($cantidadCortada <= 0) {
                continue;
            }

            $ejecutadoTotal = (float) Avance::where('id_item', $item->id)->sum('cantidad');

            $cortadoPrevio = (float) OtCorteDetalle::where('orden_item_id', $item->id)
                ->whereHas('corte', fn ($q) => $q->where('ot_id', $ot->id)->where('estatus', '!=', 'void'))
                ->sum('cantidad_cortada');

            $ejecutadoNoCortado = $ejecutadoTotal - $cortadoPrevio;
            $itemNombre = $item->descripcion ?? 'Item #' . $item->id;

            if ($cantidadCortada > $ejecutadoNoCortado) {
                throw new \InvalidArgumentException(
                    "Item \"{$itemNombre}\": cantidad a cortar ({$cantidadCortada}) " .
                    "excede el ejecutado no cortado ({$ejecutadoNoCortado})."
                );
            }
            if ($cantidadCortada > (float) $item->cantidad_planeada) {
                throw new \InvalidArgumentException(
                    "Item \"{$itemNombre}\": cantidad a cortar ({$cantidadCortada}) " .
                    "excede lo contratado ({$item->cantidad_planeada})."
                );
            }

            $precioUnit = (float) ($item->precio_unitario ?? 0);
            $importe    = round($cantidadCortada * $precioUnit, 2);
            $montoTotal += $importe;

            $detallesCrear[] = [
                'ot_servicio_id'           => null,
                'orden_item_id'            => $item->id,
                'concepto_descripcion'     => $itemNombre,
                'cantidad_cortada'         => $cantidadCortada,
                'precio_unitario_snapshot' => $precioUnit,
                'importe_snapshot'         => $importe,
            ];
        }

        return [$detallesCrear, $montoTotal];
    }

    // ─────────────────────────────────────────────────────────
    // OT Hija (remanentes)
    // ─────────────────────────────────────────────────────────

    protected function crearOrdenHija(Orden $ot): Orden
    {
        $splitIndex = $ot->nextSplitIndex();

        $desc = $ot->descripcion_general
            ? '[Hija de OT #' . $ot->id . '] ' . $ot->descripcion_general
            : 'OT hija generada por corte de OT #' . $ot->id;

        return Orden::create([
            'id_solicitud'        => $ot->id_solicitud,
            'id_centrotrabajo'    => $ot->id_centrotrabajo,
            'id_servicio'         => $ot->id_servicio,
            'id_area'             => $ot->id_area,
            'team_leader_id'      => $ot->team_leader_id,
            'descripcion_general' => $desc,
            'estatus'             => $ot->estatus,
            'ot_status'           => 'active',
            'parent_ot_id'        => $ot->parent_ot_id ?: $ot->id,
            'split_index'         => $splitIndex,
            'subtotal'            => 0,
            'iva'                 => 0,
            'total'               => 0,
        ]);
    }

    protected function crearOtHijaMultiServicio(Orden $ot): ?Orden
    {
        $serviciosRemanente = [];

        foreach ($ot->otServicios as $srv) {
            $totalCortado = (float) OtCorteDetalle::where('ot_servicio_id', $srv->id)
                ->whereHas('corte', fn ($q) => $q->where('ot_id', $ot->id)->where('estatus', '!=', 'void'))
                ->sum('cantidad_cortada');

            $remanente = (float) $srv->cantidad - $totalCortado;

            if ($remanente > 0) {
                $serviciosRemanente[] = ['srv' => $srv, 'remanente' => $remanente];
            }
        }

        if (empty($serviciosRemanente)) {
            return null;
        }

        $otHija = $this->crearOrdenHija($ot);

        foreach ($serviciosRemanente as $data) {
            $srvOrigen = $data['srv'];
            $remanente = $data['remanente'];

            $nuevoSrv = OTServicio::create([
                'ot_id'           => $otHija->id,
                'servicio_id'     => $srvOrigen->servicio_id,
                'tipo_cobro'      => $srvOrigen->tipo_cobro,
                'cantidad'        => $remanente,
                'precio_unitario' => $srvOrigen->precio_unitario,
                'subtotal'        => round($remanente * (float) $srvOrigen->precio_unitario, 2),
                'origen'          => 'corte',
                'nota'            => 'Remanente de OT #' . $ot->id . ', servicio #' . $srvOrigen->id,
            ]);

            $itemsOrigen = $srvOrigen->items()->get();
            $totalPlaneadoOrigen = (float) $itemsOrigen->sum('planeado');

            foreach ($itemsOrigen as $itemOrigen) {
                $ratio = $totalPlaneadoOrigen > 0
                    ? (float) $itemOrigen->planeado / $totalPlaneadoOrigen
                    : 1 / max(1, $itemsOrigen->count());

                $planeadoItem = (int) round($remanente * $ratio);

                if ($planeadoItem > 0) {
                    OTServicioItem::create([
                        'ot_servicio_id'   => $nuevoSrv->id,
                        'descripcion_item' => $itemOrigen->descripcion_item,
                        'tamano'           => $itemOrigen->tamano ?? null,
                        'planeado'         => $planeadoItem,
                        'completado'       => 0,
                        'faltante'         => 0,
                        'precio_unitario'  => $itemOrigen->precio_unitario,
                        'subtotal'         => round($planeadoItem * (float) ($itemOrigen->precio_unitario ?? 0), 2),
                    ]);
                }
            }
        }

        $otHija->recalcTotals();

        return $otHija;
    }

    protected function crearOtHijaTradicional(Orden $ot): ?Orden
    {
        $itemsRemanente = [];

        foreach ($ot->items as $item) {
            $totalCortado = (float) OtCorteDetalle::where('orden_item_id', $item->id)
                ->whereHas('corte', fn ($q) => $q->where('ot_id', $ot->id)->where('estatus', '!=', 'void'))
                ->sum('cantidad_cortada');

            $remanente = (float) $item->cantidad_planeada - $totalCortado;

            if ($remanente > 0) {
                $itemsRemanente[] = ['item' => $item, 'remanente' => $remanente];
            }
        }

        if (empty($itemsRemanente)) {
            return null;
        }

        $otHija = $this->crearOrdenHija($ot);

        foreach ($itemsRemanente as $data) {
            $itemOrigen = $data['item'];
            $remanente  = $data['remanente'];

            OrdenItem::create([
                'id_orden'          => $otHija->id,
                'descripcion'       => $itemOrigen->descripcion,
                'tamano'            => $itemOrigen->tamano ?? null,
                'cantidad_planeada' => $remanente,
                'cantidad_real'     => 0,
                'faltantes'         => 0,
                'precio_unitario'   => $itemOrigen->precio_unitario,
                'subtotal'          => round($remanente * (float) ($itemOrigen->precio_unitario ?? 0), 2),
            ]);
        }

        // Recalcular totales de la OT hija si existe el método
        if (method_exists($otHija, 'recalcTotals')) {
            $otHija->recalcTotals();
        }

        return $otHija;
    }

    // ─────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────

    protected function actualizarEstatusOt(Orden $ot, bool $hayRemanente): void
    {
        $actualizaciones = [
            'ot_status' => $hayRemanente ? 'partial' : 'closed',
        ];

        // Tras generar corte, la OT origen queda cerrada para producción
        // y debe entrar al flujo normal de calidad/cliente/facturación.
        if (!in_array((string)$ot->estatus, ['autorizada_cliente', 'facturada', 'entregada'], true)) {
            $actualizaciones['estatus'] = 'completada';
        }

        if (empty($ot->calidad_resultado)) {
            $actualizaciones['calidad_resultado'] = 'pendiente';
        }

        $ot->update($actualizaciones);
    }

    /**
     * Obtiene el detalle completo de un corte (ambos flujos).
     */
    public function getCorteDetalle(OtCorte $corte): array
    {
        $corte->load([
            'ot.centro',
            'ot.servicio',
            'detalles.otServicio.servicio',
            'detalles.ordenItem',
            'otHija',
            'createdBy',
        ]);

        return [
            'id'             => $corte->id,
            'folio_corte'    => $corte->folio_corte,
            'ot_id'          => $corte->ot_id,
            'ot_folio'       => 'OT-' . $corte->ot_id,
            'periodo_inicio' => $corte->periodo_inicio->format('Y-m-d'),
            'periodo_fin'    => $corte->periodo_fin->format('Y-m-d'),
            'estatus'        => $corte->estatus,
            'monto_total'    => (float) $corte->monto_total,
            'created_by'     => $corte->createdBy ? [
                'id'   => $corte->createdBy->id,
                'name' => $corte->createdBy->name,
            ] : null,
            'ot_hija' => $corte->otHija ? [
                'id'     => $corte->otHija->id,
                'folio'  => 'OT-' . $corte->otHija->id,
                'status' => $corte->otHija->ot_status,
            ] : null,
            'detalles'  => $corte->detalles->map(fn ($d) => [
                'id'                       => $d->id,
                'concepto_tipo'            => $d->ot_servicio_id ? 'servicio' : 'item',
                'ot_servicio_id'           => $d->ot_servicio_id,
                'orden_item_id'            => $d->orden_item_id,
                'servicio_nombre'          => $d->nombre_concepto,
                'cantidad_cortada'         => (float) $d->cantidad_cortada,
                'precio_unitario_snapshot' => (float) $d->precio_unitario_snapshot,
                'importe_snapshot'         => (float) $d->importe_snapshot,
            ])->toArray(),
            'created_at' => $corte->created_at->toIso8601String(),
        ];
    }
}
