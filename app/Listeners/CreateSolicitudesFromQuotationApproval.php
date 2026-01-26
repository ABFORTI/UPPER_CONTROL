<?php

namespace App\Listeners;

use App\Events\QuotationApproved;
use App\Models\Cotizacion;
use App\Models\Solicitud;
use App\Services\QuotationApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSolicitudesFromQuotationApproval
{
    public function handle(QuotationApproved $event): void
    {
        if (!Schema::hasTable('solicitudes')) {
            return;
        }

        DB::transaction(function () use ($event) {
            $cotizacion = Cotizacion::whereKey($event->cotizacionId)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotencia: si ya existen solicitudes para esta cotización, no volver a crear.
            if (Solicitud::where('id_cotizacion', (int)$cotizacion->id)->exists()) {
                return;
            }

            $cotizacion->loadMissing([
                'items',
                'items.servicios',
                'items.servicios.servicio',
            ]);

            $approval = app(QuotationApprovalService::class);

            $hasIdArea = Schema::hasColumn('solicitudes', 'id_area');
            $hasIdCentroCosto = Schema::hasColumn('solicitudes', 'id_centrocosto');
            $hasIdMarca = Schema::hasColumn('solicitudes', 'id_marca');
            $hasTamanosJson = Schema::hasColumn('solicitudes', 'tamanos_json');
            $hasTotals = Schema::hasColumn('solicitudes', 'subtotal')
                && Schema::hasColumn('solicitudes', 'iva')
                && Schema::hasColumn('solicitudes', 'total');
            $hasQuotationItemId = Schema::hasColumn('solicitudes', 'id_cotizacion_item');
            $hasMetadataJson = Schema::hasColumn('solicitudes', 'metadata_json');

            foreach ($cotizacion->items as $item) {
                foreach ($item->servicios as $line) {
                    $servicioId = (int)$line->id_servicio;

                    $tamano = $line->tamano ? (string)$line->tamano : null;

                    // cantidad (int) para compatibilidad con solicitudes existentes
                    $cantidad = (int)($line->cantidad ?? $item->cantidad ?? 1);
                    if ($cantidad <= 0) {
                        $cantidad = 1;
                    }

                    $folio = $approval->generateSolicitudFolio((int)$cotizacion->id_centrotrabajo);

                    $data = [
                        'folio' => $folio,
                        'id_cliente' => (int)$cotizacion->id_cliente,
                        'id_centrotrabajo' => (int)$cotizacion->id_centrotrabajo,
                        'id_servicio' => $servicioId,
                        'tamano' => $tamano,
                        'descripcion' => (string)($item->descripcion ?? ''),
                        'cantidad' => $cantidad,
                        'notas' => trim((string)($cotizacion->notas ?? '') . "\n" . (string)($item->notas ?? '') . "\n" . (string)($line->notes ?? '')) ?: null,
                        'estatus' => 'pendiente',
                        'id_cotizacion' => (int)$cotizacion->id,
                        'id_cotizacion_item_servicio' => (int)$line->id,
                    ];

                    if ($hasIdArea) {
                        $data['id_area'] = $cotizacion->id_area ? (int)$cotizacion->id_area : null;
                    }
                    if ($hasIdCentroCosto) {
                        $data['id_centrocosto'] = (int)$cotizacion->id_centrocosto;
                    }
                    if ($hasIdMarca) {
                        $data['id_marca'] = $cotizacion->id_marca ? (int)$cotizacion->id_marca : null;
                    }
                    if ($hasTamanosJson) {
                        $data['tamanos_json'] = $line->tamanos_json;
                    }
                    if ($hasTotals) {
                        $data['subtotal'] = (float)($line->subtotal ?? 0);
                        $data['iva'] = (float)($line->iva ?? 0);
                        $data['total'] = (float)($line->total ?? 0);
                    }
                    if ($hasQuotationItemId) {
                        $data['id_cotizacion_item'] = (int)$item->id;
                    }
                    if ($hasMetadataJson) {
                        $data['metadata_json'] = [
                            'quotation' => [
                                'id' => (int)$cotizacion->id,
                                'folio' => (string)($cotizacion->folio ?? ''),
                                'currency' => (string)($cotizacion->currency ?? ''),
                            ],
                            'item' => [
                                'id' => (int)$item->id,
                                'description' => (string)($item->descripcion ?? ''),
                                'quantity' => (int)($item->cantidad ?? 1),
                                'product_name' => $item->product_name ?? null,
                                'quantity_decimal' => $item->quantity ?? null,
                                'unit' => $item->unit ?? null,
                                'metadata' => $item->metadata ?? null,
                            ],
                            'service_line' => [
                                'id' => (int)$line->id,
                                'service_id' => $servicioId,
                                'service_name' => $line->servicio?->nombre,
                                'tamano' => $tamano,
                                'cantidad_int' => $cantidad,
                                'qty_decimal' => $line->qty !== null ? (float)$line->qty : null,
                                'unit_price' => $line->precio_unitario !== null ? (float)$line->precio_unitario : null,
                                'subtotal' => $line->subtotal !== null ? (float)$line->subtotal : null,
                                'iva' => $line->iva !== null ? (float)$line->iva : null,
                                'total' => $line->total !== null ? (float)$line->total : null,
                            ],
                        ];
                    }

                    Solicitud::create($data);
                }
            }
        });
    }
}
