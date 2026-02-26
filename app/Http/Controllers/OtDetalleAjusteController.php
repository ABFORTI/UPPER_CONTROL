<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOtDetalleAjusteRequest;
use App\Models\Orden;
use App\Models\OrdenItem;
use App\Models\OtAjusteDetalle;
use App\Models\OTServicioItem;
use Illuminate\Support\Facades\DB;

class OtDetalleAjusteController extends Controller
{
    public function store(StoreOtDetalleAjusteRequest $request, Orden $ot, OTServicioItem $detalle)
    {
        $this->authorize('registrarAjuste', $ot);

        $detalle->loadMissing('otServicio');
        if (! $detalle->otServicio || (int) $detalle->otServicio->ot_id !== (int) $ot->id) {
            return back()->withErrors(['detalle' => 'El detalle no pertenece a la OT seleccionada.']);
        }

        if (in_array((string) ($ot->ot_status ?? 'active'), ['partial', 'closed', 'canceled'], true)
            || in_array((string) $ot->estatus, ['completada', 'autorizada_cliente', 'facturada', 'entregada'], true)) {
            return back()->withErrors(['orden' => 'La OT está cerrada/facturada y ya no admite ajustes.']);
        }

        $data = $request->validated();

        DB::transaction(function () use ($ot, $detalle, $data, $request) {
            OtAjusteDetalle::create([
                'ot_id' => $ot->id,
                'ot_detalle_id' => $detalle->id,
                'tipo' => $data['tipo'],
                'cantidad' => (int) $data['cantidad'],
                'motivo' => $data['motivo'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            $detalle->otServicio->recalcularSubtotalDesdeCobrable();

            $subtotalOT = (float) $ot->otServicios()->sum('subtotal');
            $iva = round($subtotalOT * 0.16, 2);
            $total = round($subtotalOT + $iva, 2);

            $ot->update([
                'subtotal' => $subtotalOT,
                'iva' => $iva,
                'total' => $total,
                'total_real' => $subtotalOT,
            ]);
        });

        activity('ordenes')
            ->performedOn($ot)
            ->causedBy($request->user())
            ->event('ajuste_detalle')
            ->withProperties([
                'detalle_id' => $detalle->id,
                'tipo' => $data['tipo'],
                'cantidad' => (int) $data['cantidad'],
                'motivo' => $data['motivo'] ?? null,
            ])
            ->log("OT #{$ot->id}: ajuste {$data['tipo']} registrado");

        return back()->with('ok', 'Ajuste registrado correctamente.');
    }

    public function storeTradicional(StoreOtDetalleAjusteRequest $request, Orden $ot, OrdenItem $item)
    {
        $this->authorize('registrarAjuste', $ot);

        if ((int) $item->id_orden !== (int) $ot->id) {
            return back()->withErrors(['item' => 'El producto no pertenece a la OT seleccionada.']);
        }

        if (in_array((string) ($ot->ot_status ?? 'active'), ['partial', 'closed', 'canceled'], true)
            || in_array((string) $ot->estatus, ['completada', 'autorizada_cliente', 'facturada', 'entregada'], true)) {
            return back()->withErrors(['orden' => 'La OT está cerrada/facturada y ya no admite ajustes.']);
        }

        $data = $request->validated();

        DB::transaction(function () use ($ot, $item, $data, $request) {
            OtAjusteDetalle::create([
                'ot_id' => $ot->id,
                'ot_detalle_id' => null,
                'orden_item_id' => $item->id,
                'tipo' => $data['tipo'],
                'cantidad' => (int) $data['cantidad'],
                'motivo' => $data['motivo'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            $item->load('ajustes');
            $met = $item->calcularMetricas();
            $precio = (float) ($item->precio_unitario ?? 0);
            if ($precio <= 0) {
                $baseQty = max(1, (int)($item->cantidad_real ?? 0), (int)($item->cantidad_planeada ?? 0));
                $precio = (float) ($item->subtotal ?? 0) / $baseQty;
            }

            $item->subtotal = round(((float) $met['total_cobrable']) * $precio, 2);
            $item->save();

            $subtotalOT = (float) $ot->items()->sum('subtotal');
            $iva = round($subtotalOT * 0.16, 2);
            $total = round($subtotalOT + $iva, 2);

            $ot->update([
                'subtotal' => $subtotalOT,
                'iva' => $iva,
                'total' => $total,
                'total_real' => $subtotalOT,
            ]);
        });

        activity('ordenes')
            ->performedOn($ot)
            ->causedBy($request->user())
            ->event('ajuste_item_tradicional')
            ->withProperties([
                'item_id' => $item->id,
                'tipo' => $data['tipo'],
                'cantidad' => (int) $data['cantidad'],
                'motivo' => $data['motivo'] ?? null,
            ])
            ->log("OT #{$ot->id}: ajuste {$data['tipo']} registrado en item tradicional");

        return back()->with('ok', 'Ajuste registrado correctamente.');
    }
}
