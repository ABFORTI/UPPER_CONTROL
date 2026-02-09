<?php

// app/Http/Controllers/OrdenController.php
namespace App\Http\Controllers;


use App\Models\Solicitud;
use App\Models\Orden;
use App\Models\OrdenItem;
use App\Models\OrdenItemProduccionSegmento;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Notifications\OtAsignada;
use App\Notifications\OtListaParaCalidad;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\Notifier;
use App\Jobs\GenerateOrdenPdf;
use App\Exports\OrdenesIndexExport;
use App\Exports\OrdenesFacturacionExport;
use Maatwebsite\Excel\Facades\Excel;

class OrdenController extends Controller
{

    private function ordenBloqueadaParaEdicionProduccion(Orden $orden): bool
    {
        if (in_array((string)$orden->estatus, ['facturada'], true)) return true;
        if ($orden->factura()->exists()) return true;
        if ($orden->facturas()->exists()) return true;
        return false;
    }

    private function ordenBloqueadaParaEdicionPrecios(Orden $orden): bool
    {
        if (in_array((string)$orden->estatus, ['autorizada_cliente','facturada'], true)) return true;
        if ($orden->factura()->exists()) return true;
        if ($orden->facturas()->exists()) return true;
        return false;
    }

    /** Actualizar precio de un segmento (solo EXTRA/FIN_DE_SEMANA) */
    public function updateSegmentoProduccion(Request $req, Orden $orden, OrdenItemProduccionSegmento $segmento)
    {
        $this->authorize('reportarAvance', $orden);
        $this->authorizeFromCentro($orden->id_centrotrabajo, $orden);

        if ($this->ordenBloqueadaParaEdicionPrecios($orden)) {
            return back()->withErrors(['orden' => 'La OT está autorizada/facturada; ya no se permite editar precios.']);
        }

        if ((int)$segmento->id_orden !== (int)$orden->id) {
            return back()->withErrors(['segmento' => 'El segmento no pertenece a esta OT.']);
        }

        if (!in_array((string)$segmento->tipo_tarifa, ['EXTRA','FIN_DE_SEMANA'], true)) {
            return back()->withErrors(['segmento' => 'Solo se puede editar el precio de segmentos EXTRA o FIN_DE_SEMANA.']);
        }

        $data = $req->validate([
            'precio_unitario' => ['required','numeric','min:0.0001'],
            'nota' => ['nullable','string','max:500'],
        ]);

        DB::transaction(function () use ($orden, $segmento, $data) {
            /** @var \App\Models\OrdenItemProduccionSegmento $seg */
            $seg = OrdenItemProduccionSegmento::where('id', $segmento->id)->lockForUpdate()->firstOrFail();
            $seg->precio_unitario = (float)$data['precio_unitario'];
            $seg->subtotal = round(((float)$seg->precio_unitario) * (int)$seg->cantidad, 2);
            if (array_key_exists('nota', $data)) {
                $seg->nota = $data['nota'] ?: null;
            }
            $seg->save();

            $item = OrdenItem::where('id', $seg->id_item)
                ->where('id_orden', $orden->id)
                ->lockForUpdate()
                ->firstOrFail();

            $segmentos = OrdenItemProduccionSegmento::where('id_orden', $orden->id)
                ->where('id_item', $item->id)
                ->lockForUpdate()
                ->get();

            $qtyTotal = (int)$segmentos->sum('cantidad');
            $subTotal = (float)$segmentos->sum('subtotal');
            $item->cantidad_real = $qtyTotal;
            $item->subtotal = $subTotal;
            $item->precio_unitario = $qtyTotal > 0 ? ($subTotal / $qtyTotal) : (float)($item->precio_unitario ?? 0);
            $item->save();

            // Recalcular totales de la OT con base en subtotales de items
            $sumSubtotales = (float)$orden->items()->selectRaw('COALESCE(SUM(subtotal),0) as t')->value('t');
            $orden->total_real = $sumSubtotales;
            $orden->subtotal = $sumSubtotales;
            $orden->iva = $orden->subtotal * 0.16;
            $orden->total = $orden->subtotal + $orden->iva;
            $orden->save();
        });

        return back()->with('ok', 'Segmento actualizado');
    }

    // PDF OT
    public function pdf(\App\Models\Orden $orden)
    {
        $this->authorize('view', $orden);

                if ($orden->pdf_path && Storage::exists($orden->pdf_path)) {
                        $abs = Storage::path($orden->pdf_path);
                        return response()->file($abs, [
                            'Content-Type' => 'application/pdf',
                            'Content-Disposition' => 'inline; filename="OT_' . $orden->id . '.pdf"'
                        ]);
                }

        // Fallback: generar al vuelo (por si el worker aún no corrió)
        // Eager loading completo para evitar N+1 queries
        $orden->load([
            'servicio',
            'centro',
            'teamLeader',
            'area',
            'items',
            'solicitud.cliente',
            'solicitud.marca',
            'aprobaciones.usuario',
            // Cargar servicios de la OT con todas sus relaciones necesarias
            'otServicios' => function($query) {
                $query->with([
                    'servicio',             // Información del servicio
                    'addedBy',              // Usuario que agregó servicio adicional
                    'items',                // Items del servicio (para totales)
                    'avances' => function($q) {
                        $q->with('createdBy') // Usuario que creó el avance
                          ->orderBy('created_at', 'asc');
                    }
                ])->orderBy('created_at', 'asc');
            }
        ]);
        
        $pdf = PDF::loadView('pdf.orden', ['orden'=>$orden])->setPaper('letter');
        return $pdf->stream("OT_{$orden->id}.pdf");
    }

    /** Form: Generar OT desde solicitud aprobada */
    public function createFromSolicitud(Solicitud $solicitud)
    {
        Log::info('CreateFromSolicitud: visit', [
            'solicitud_id' => $solicitud->id,
            'centro_id'    => $solicitud->id_centrotrabajo,
            'servicio_id'  => $solicitud->id_servicio,
            'estatus'      => $solicitud->estatus,
        ]);
        // (SIN CAMBIOS) Lógica original reinsertada para evitar problemas del intento de refactor
        $this->authorize('createFromSolicitud', [Orden::class, $solicitud->id_centrotrabajo]);
        $this->authorizeFromCentro($solicitud->id_centrotrabajo);
        if ($solicitud->estatus !== 'aprobada') abort(422, 'La solicitud no está aprobada.');
        if ($solicitud->ordenes()->exists()) {
            return redirect()->route('solicitudes.show', $solicitud->id)
                ->withErrors(['ot' => 'Ya existe una Orden de Trabajo para esta solicitud.']);
        }

        // Cargar relaciones necesarias
        $solicitud->load(['servicio','centro','tamanos','centroCosto','marca','area','servicios.servicio']);

        // NUEVO: Detectar si es multi-servicio
        $esMultiServicio = $solicitud->servicios()->exists();
        
        // Si es multi-servicio, crear OT directamente (sin formulario)
        if ($esMultiServicio) {
            // Crear OT sin items (multi-servicio no requiere items tradicionales)
            DB::beginTransaction();
            try {
                $orden = Orden::create([
                    'folio'            => $this->buildFolioOT($solicitud->id_centrotrabajo),
                    'id_solicitud'     => $solicitud->id,
                    'id_centrotrabajo' => $solicitud->id_centrotrabajo,
                    'id_servicio'      => null, // Multi-servicio
                    'id_area'          => $solicitud->id_area,
                    'team_leader_id'   => null,
                    'descripcion_general' => $solicitud->descripcion ?? '',
                    'estatus'          => 'generada',
                    'total_planeado'   => $solicitud->cantidad ?? 0,
                    'total_real'       => 0,
                    'calidad_resultado'=> 'pendiente',
                ]);

                // Copiar servicios de solicitud a OT
                \Log::info('🔄 Copiando servicios a OT (createFromSolicitud)', [
                    'orden_id' => $orden->id,
                    'count' => $solicitud->servicios->count()
                ]);
                
                foreach ($solicitud->servicios as $solServicio) {
                    $otServicio = \App\Models\OTServicio::create([
                        'ot_id'            => $orden->id,
                        'servicio_id'      => $solServicio->servicio_id,
                        'tipo_cobro'       => $solServicio->tipo_cobro,
                        'cantidad'         => $solServicio->cantidad,
                        'precio_unitario'  => $solServicio->precio_unitario,
                        'subtotal'         => $solServicio->subtotal,
                    ]);
                    
                    // Crear item por defecto para este servicio
                    \App\Models\OTServicioItem::create([
                        'ot_servicio_id'   => $otServicio->id,
                        'descripcion_item' => $solServicio->servicio->nombre ?? 'Item',
                        'planeado'         => $solServicio->cantidad,
                        'completado'       => 0,
                    ]);
                    
                    \Log::info('✅ Servicio + Item creado', [
                        'ot_servicio_id' => $otServicio->id,
                        'cantidad' => $solServicio->cantidad
                    ]);
                }

                // Recalcular totales
                $orden->recalcTotals();

                DB::commit();

                // Activity log
                $this->act('ordenes')
                    ->performedOn($orden)
                    ->event('generar_ot_multi')
                    ->log("OT #{$orden->id} generada desde solicitud multi-servicio {$solicitud->folio}");

                // Notificar a calidad
                Notifier::toRoleInCentro(
                    'calidad',
                    $orden->id_centrotrabajo,
                    'OT multi-servicio generada',
                    "Se generó la OT #{$orden->id} con múltiples servicios.",
                    route('ordenes.show', $orden->id)
                );

                // Generar PDF en background
                \App\Jobs\GenerateOrdenPdf::dispatch($orden->id)->onQueue('pdfs');

                return redirect()->route('ordenes.show', $orden->id)
                    ->with('ok', 'OT multi-servicio creada correctamente');
                    
            } catch (\Throwable $ex) {
                DB::rollBack();
                \Log::error('Error creando OT multi-servicio', [
                    'solicitud_id' => $solicitud->id,
                    'error' => $ex->getMessage()
                ]);
                return redirect()->route('solicitudes.show', $solicitud->id)
                    ->with('error', 'Error al crear OT: ' . $ex->getMessage());
            }
        }

        // Modo per-centro: detectar tamaños configurados en el centro de la solicitud
        $usaTamanos = \App\Models\ServicioCentro::where('id_centrotrabajo',$solicitud->id_centrotrabajo)
            ->where('id_servicio',$solicitud->id_servicio)
            ->whereHas('tamanos')
            ->exists();
        $prefill = [];
        if ($usaTamanos && $solicitud->tamanos->count() > 0) {
            foreach ($solicitud->tamanos as $t) {
                $prefill[] = [
                    'tamano'      => (string)($t->tamano ?? ''),
                    'cantidad'    => (int)($t->cantidad ?? 0),
                    'descripcion' => null,
                    'editable'    => false,
                ];
            }
        } else {
            $prefill[] = [
                'descripcion' => $solicitud->descripcion ?? 'Item',
                'cantidad'    => (int)($solicitud->cantidad ?? 1),
                'tamano'      => null,
                'editable'    => true,
            ];
        }

        $teamLeaders = User::role('team_leader')
            ->where('centro_trabajo_id', $solicitud->id_centrotrabajo)
            ->select('id','name')->orderBy('name')->get();

        $pricing = app(\App\Domain\Servicios\PricingService::class);
        $ivaRate = 0.16; $cotLines = []; $sub = 0.0;
        if ($usaTamanos && $solicitud->tamanos && $solicitud->tamanos->count() > 0) {
            foreach ($solicitud->tamanos as $t) {
                $tam = (string)($t->tamano ?? '');
                $cant = (int)($t->cantidad ?? 0);
                if ($cant <= 0) continue;
                $pu = (float)$pricing->precioUnitario($solicitud->id_centrotrabajo, $solicitud->id_servicio, $tam);
                $lineSub = $pu * $cant; $sub += $lineSub;
                $cotLines[] = ['label'=>ucfirst($tam), 'cantidad'=>$cant, 'pu'=>$pu, 'subtotal'=>$lineSub];
            }
        } elseif ($usaTamanos) {
            $cotLines[] = ['label'=>'Item', 'cantidad'=>(int)($solicitud->cantidad ?? 0), 'pu'=>0.0, 'subtotal'=>0.0];
        } else {
            $pu = (float)$pricing->precioUnitario($solicitud->id_centrotrabajo, $solicitud->id_servicio, null);
            $sub = $pu * (int)($solicitud->cantidad ?? 0);
            $cotLines[] = ['label'=>'Item', 'cantidad'=>(int)($solicitud->cantidad ?? 0), 'pu'=>$pu, 'subtotal'=>$sub];
        }
        $cot = ['lines'=>$cotLines, 'subtotal'=>$sub, 'iva_rate'=>$ivaRate, 'iva'=>$sub*$ivaRate, 'total'=>$sub*(1+$ivaRate)];

        return Inertia::render('Ordenes/CreateFromSolicitud', [
            'solicitud'           => [
                'id' => $solicitud->id,
                'descripcion' => $solicitud->descripcion,
                'cantidad' => (int)$solicitud->cantidad,
                'id_servicio' => (int)$solicitud->id_servicio,
                'id_centrotrabajo' => (int)$solicitud->id_centrotrabajo,
                'id_area' => $solicitud->id_area ? (int)$solicitud->id_area : null,
                'servicio' => [ 'nombre' => $solicitud->servicio->nombre ?? null ],
                'centro'   => [ 'nombre' => $solicitud->centro->nombre ?? null ],
                'centroCosto' => $solicitud->centroCosto ? $solicitud->centroCosto->only(['id','nombre']) : null,
                'marca'       => $solicitud->marca ? $solicitud->marca->only(['id','nombre']) : null,
                'area'        => $solicitud->area ? $solicitud->area->only(['id','nombre']) : null,
            ],
            'folio'               => $this->buildFolioOT($solicitud->id_centrotrabajo),
            'teamLeaders'         => $teamLeaders,
            'prefill'             => $prefill,
            'usaTamanos'          => $usaTamanos,
            'cantidadTotal'       => (int)($solicitud->cantidad ?? 1),
            'descripcionGeneral'  => $solicitud->descripcion ?? '',
            'cotizacion'          => $cot,
            'urls'                => [ 'store' => route('ordenes.storeFromSolicitud', $solicitud) ],
            'areas'               => \App\Models\Area::where('id_centrotrabajo', $solicitud->id_centrotrabajo)->activas()->orderBy('nombre')->get(),
        ]);
    }

    /** POST: Guardar OT (sin pricing) */
    public function storeFromSolicitud(Request $req, Solicitud $solicitud)
    {
        $this->authorize('createFromSolicitud', [Orden::class, $solicitud->id_centrotrabajo]);
        $this->authorizeFromCentro($solicitud->id_centrotrabajo);
        if ($solicitud->estatus !== 'aprobada') abort(422, 'La solicitud no está aprobada.');
        if ($solicitud->ordenes()->exists()) {
            return redirect()->route('solicitudes.show', $solicitud->id)
                ->withErrors(['ot' => 'Ya existe una Orden de Trabajo para esta solicitud.']);
        }

        $solicitud->load('servicio', 'tamanos', 'servicios.servicio');
        
        // NUEVO: Detectar si es multi-servicio
        $esMultiServicio = $solicitud->servicios()->exists();
        
        $usaTamanos = \App\Models\ServicioCentro::where('id_centrotrabajo',$solicitud->id_centrotrabajo)
            ->where('id_servicio',$solicitud->id_servicio)
            ->whereHas('tamanos')
            ->exists();

        // Validación base
        $data = $req->validate([
            'team_leader_id' => ['nullable','integer','exists:users,id'],
            'id_area' => ['nullable','integer','exists:areas,id'],
            'separar_items'  => ['nullable','boolean'],
            'items'          => ['required','array','min:1'],
            'items.*.cantidad' => ['required','integer','min:1'],
            'items.*.descripcion' => ['nullable','string','max:255'],
            'items.*.tamano' => ['nullable','string'],
        ]);

        // Si el cliente ya seleccionó área en la solicitud, el coordinador NO puede cambiarla
        $requestedAreaId = $data['id_area'] ?? null;
        if (!empty($solicitud->id_area)) {
            if (!empty($requestedAreaId) && (int)$requestedAreaId !== (int)$solicitud->id_area) {
                return back()->withErrors([
                    'id_area' => 'El área fue seleccionada por el cliente y no se puede cambiar.'
                ]);
            }
            $data['id_area'] = (int)$solicitud->id_area;
        }

        // Validar pertenencia del área (si existe) al centro de la solicitud
        if (!empty($data['id_area'])) {
            $area = \App\Models\Area::find($data['id_area']);
            if (!$area || (int)$area->id_centrotrabajo !== (int)$solicitud->id_centrotrabajo) {
                return back()->withErrors([
                    'id_area' => 'El área seleccionada no pertenece al centro de la solicitud.'
                ]);
            }
        }

        $separarItems = (bool)($data['separar_items'] ?? false);

        if ($usaTamanos) {
            if ($solicitud->tamanos->count() > 0) {
                // Servicios CON tamaños: validar tamaños obligatorios y cantidades exactas
                $req->validate([
                    'items.*.tamano' => ['required','in:chico,mediano,grande,jumbo'],
                ]);

                $expectedItems = $solicitud->tamanos->keyBy('tamano')->map(fn($t) => (int)$t->cantidad)->toArray();
                foreach ($data['items'] as $item) {
                    $tamano = $item['tamano'] ?? null;
                    if (!isset($expectedItems[$tamano])) {
                        return back()->withErrors(['items' => "El tamaño '{$tamano}' no existe en la solicitud aprobada."]);
                    }
                    if ((int)$item['cantidad'] !== $expectedItems[$tamano]) {
                        return back()->withErrors(['items' => "La cantidad del tamaño '{$tamano}' no coincide con la solicitud aprobada ({$expectedItems[$tamano]} esperado)."]);
                    }
                }
            } else {
                // Flujo diferido: permitir crear OT sin desglose; validar solo suma de cantidades
                $cantidadTotal = (int)$solicitud->cantidad;
                $sumaCantidades = collect($data['items'])->sum(fn($i) => (int)($i['cantidad'] ?? 0));
                if ($sumaCantidades !== $cantidadTotal) {
                    return back()->withErrors([
                        'items' => "La suma de las cantidades ({$sumaCantidades}) debe ser igual al total aprobado ({$cantidadTotal})."
                    ]);
                }
            }
        } else {
            // Servicios SIN tamaños
            if ($separarItems) {
                // SI se activa separación: validar descripciones y suma
                $req->validate([
                    'items.*.descripcion' => ['required','string','max:255'],
                ]);
                
                // VALIDACIÓN CRÍTICA: Suma de cantidades debe ser igual a cantidad total aprobada
                $cantidadTotal = (int)$solicitud->cantidad;
                $sumaCantidades = collect($data['items'])->sum(fn($i) => (int)($i['cantidad'] ?? 0));
                
                if ($sumaCantidades !== $cantidadTotal) {
                    return back()->withErrors([
                        'items' => "La suma de las cantidades de los ítems ({$sumaCantidades}) no coincide con la cantidad total aprobada ({$cantidadTotal})."
                    ]);
                }
            } else {
                // NO se separa: descripción puede ser opcional o usar la general
                // No es necesaria validación de suma
            }
        }

        $orden = DB::transaction(function () use ($solicitud, $data, $usaTamanos, $separarItems, $esMultiServicio) {
            $totalPlan = collect($data['items'])->sum(fn($i) => (int)($i['cantidad'] ?? 0));

            $orden = Orden::create([
                'folio'            => $this->buildFolioOT($solicitud->id_centrotrabajo),
                'id_solicitud'     => $solicitud->id,
                'id_centrotrabajo' => $solicitud->id_centrotrabajo,
                'id_servicio'      => $solicitud->id_servicio,
                'id_area'          => $data['id_area'] ?? null,
                'team_leader_id'   => $data['team_leader_id'] ?? null,
                'descripcion_general' => $solicitud->descripcion ?? '',
                'estatus'          => !empty($data['team_leader_id']) ? 'asignada' : 'generada',
                'total_planeado'   => $totalPlan,
                'total_real'       => 0,
                'calidad_resultado'=> 'pendiente',
            ]);

            // NUEVO: Si es multi-servicio, copiar todos los servicios a ot_servicios
            if ($esMultiServicio) {
                \Log::info('🔄 Copiando servicios de solicitud a OT', ['orden_id' => $orden->id, 'count' => $solicitud->servicios->count()]);
                foreach ($solicitud->servicios as $solServicio) {
                    \App\Models\OTServicio::create([
                        'ot_id'            => $orden->id,
                        'servicio_id'      => $solServicio->servicio_id,
                        'tipo_cobro'       => $solServicio->tipo_cobro,
                        'cantidad'         => $solServicio->cantidad,
                        'precio_unitario'  => $solServicio->precio_unitario,
                        'subtotal'         => $solServicio->subtotal,
                    ]);
                }
                // Recalcular totales de la orden desde servicios
                $orden->recalcTotals();
            } else {
                // Flujo TRADICIONAL: crear items de la orden (sin servicios múltiples)
                // Resolver precios unitarios por item
                $pricing = app(\App\Domain\Servicios\PricingService::class);
                $sub = 0.0;
            
                foreach ($data['items'] as $it) {
                    $tamano = $it['tamano'] ?? null;
                    $descripcion = $it['descripcion'] ?? null;
                    
                    // Si no hay descripción específica, usar la descripción general de la solicitud
                    if (empty($descripcion)) {
                        $descripcion = $solicitud->descripcion ?? 'Sin descripción';
                    }
                    
                    // Flujo diferido (usa_tamanos sin desglose): PU = 0 hasta finalizar OT
                    $pu = ($usaTamanos && $solicitud->tamanos->count() === 0)
                        ? 0.0
                        : (float)$pricing->precioUnitario($solicitud->id_centrotrabajo, $solicitud->id_servicio, $tamano);

                    OrdenItem::create([
                        'id_orden'          => $orden->id,
                        'descripcion'       => $descripcion,
                        'tamano'            => $tamano,
                        'cantidad_planeada' => (int)$it['cantidad'],
                        'precio_unitario'   => $pu,
                        'subtotal'          => $pu * (int)$it['cantidad'],
                    ]);
                    $sub += $pu * (int)$it['cantidad'];
                }

                // Totales con IVA
                $ivaRate = 0.16; $iva = $sub * $ivaRate; $total = $sub + $iva;
                $orden->subtotal = $sub; $orden->iva = $iva; $orden->total = $total; $orden->save();
            }

            return $orden;
        });
        $this->act('ordenes')
            ->performedOn($orden)
            ->event('generar_ot')
            ->withProperties(['team_leader_id' => $orden->team_leader_id])
            ->log("OT #{$orden->id} generada desde solicitud {$solicitud->folio}");

        // Notificar al TL si fue asignado
        if ($orden->team_leader_id) {
            $teamLeader = User::find($orden->team_leader_id);
            if ($teamLeader) {
                try { $teamLeader->notify(new OtAsignada($orden)); }
                catch (\Throwable $e) {
                    Log::warning('storeFromSolicitud: fallo al notificar TL (ignorado)', [
                        'orden_id' => $orden->id,
                        'tl_id'    => $teamLeader->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }
        // Notificar a calidad del centro
        Notifier::toRoleInCentro(
            'calidad',
            $orden->id_centrotrabajo,
            'OT generada',
            "Se generó la OT #{$orden->id} (pendiente de revisión al completar).",
            route('ordenes.show',$orden->id)
        );

    GenerateOrdenPdf::dispatch($orden->id)->onQueue('pdfs');

        return redirect()->route('ordenes.show', $orden->id)->with('ok','OT creada correctamente');
    }

    /** Registrar avances */
    public function registrarAvance(Request $req, Orden $orden)
    {
        $this->authorize('reportarAvance', $orden);
        $this->authorizeFromCentro($orden->id_centrotrabajo, $orden);

        if ($this->ordenBloqueadaParaEdicionProduccion($orden)) {
            return back()->withErrors(['orden' => 'La OT está facturada o bloqueada; ya no se permite registrar producción.']);
        }

        // Log de entrada para diagnóstico en prod (no contiene archivos)
        $invocationId = uniqid('invoke_', true);
        Log::info('🔵 INICIO registrarAvance', [
            'invocation_id' => $invocationId,
            'orden_id' => $orden->id,
            'user_id'  => optional($req->user())->id,
            'items'    => $req->input('items'),
            'timestamp' => now()->format('Y-m-d H:i:s.u'),
        ]);

        // Detectar si es OT multi-servicio ANTES de validar
        $esMultiServicio = $orden->otServicios()->exists();
        
        // Validación condicional según tipo de OT
        $rules = [
            'id_servicio'           => ['nullable','integer','exists:ot_servicios,id'],
            'items'                 => ['required','array','min:1'],
            'items.*.id_item'       => ['required','integer'],
            'items.*.cantidad'      => ['required','integer','min:1'],
            'comentario'            => ['nullable','string','max:500'],
            'tarifa_tipo'           => ['nullable','in:NORMAL,EXTRA,FIN_DE_SEMANA'],
            'precio_unitario_manual'=> ['nullable','numeric','min:0.0001'],
        ];
        
        // Validar tabla correcta según tipo de OT
        if ($esMultiServicio) {
            $rules['items.*.id_item'][] = 'exists:ot_servicio_items,id';
        } else {
            $rules['items.*.id_item'][] = 'exists:orden_items,id';
        }
        
        $data = $req->validate($rules);
        
        // Si es multi-servicio, validar que se haya seleccionado servicio
        if ($esMultiServicio && empty($data['id_servicio'])) {
            return back()->withErrors(['id_servicio' => 'Selecciona un servicio para registrar el avance.']);
        }
        
        // Si se proporcionó id_servicio, validar que pertenece a esta OT
        $otServicio = null;
        if (!empty($data['id_servicio'])) {
            $otServicio = $orden->otServicios()->where('id', $data['id_servicio'])->first();
            if (!$otServicio) {
                return back()->withErrors(['id_servicio' => 'El servicio seleccionado no pertenece a esta OT.']);
            }
        }

        $tipoTarifa = (string)($data['tarifa_tipo'] ?? 'NORMAL');
        $precioManual = array_key_exists('precio_unitario_manual', $data)
            ? (float)($data['precio_unitario_manual'] ?? 0)
            : null;
        if ($tipoTarifa !== 'NORMAL' && (!$precioManual || $precioManual <= 0)) {
            return back()->withErrors([
                'precio_unitario_manual' => 'Captura un precio unitario válido para EXTRA / FIN_DE_SEMANA.'
            ]);
        }

        $comentarioFinal = $req->comentario;
        if ($tipoTarifa !== 'NORMAL') {
            $tag = '[TARIFA '.str_replace('_',' ', $tipoTarifa).': $'.number_format((float)$precioManual, 4, '.', '').']';
            $comentarioFinal = trim($tag.' '.(string)($comentarioFinal ?? ''));
        }

        // Validación adicional: todos los items deben pertenecer a la misma OT
        // Solo validar si NO es multi-servicio (en multi-servicio se valida dentro de la transacción)
        if (!$esMultiServicio) {
            $ids = collect($data['items'])->pluck('id_item')->map(fn($v)=>(int)$v)->all();
            $count = \App\Models\OrdenItem::whereIn('id', $ids)->where('id_orden', $orden->id)->count();
            if ($count !== count($ids)) {
                Log::warning('RegistrarAvance: id_item ajeno a la OT', [
                    'orden_id' => $orden->id,
                    'ids' => $ids,
                    'count_validos' => $count,
                ]);
                return back()->withErrors(['items' => 'Hay ítems que no pertenecen a esta OT. Actualiza la página e inténtalo de nuevo.']);
            }
        }

        $justCompleted = false;
        try {
        DB::transaction(function () use ($orden, $data, $req, &$justCompleted, $tipoTarifa, $precioManual, $comentarioFinal, $esMultiServicio, $otServicio, $invocationId) {
            $pricing = app(\App\Domain\Servicios\PricingService::class);
            $cantAplicada = [];
            
            // Si es multi-servicio, trabajar con ot_servicio_items
            if ($esMultiServicio) {
                $totalCantidadRegistrada = 0;
                
                foreach ($data['items'] as $i) {
                    $item = \App\Models\OTServicioItem::where('id', $i['id_item'])
                        ->where('ot_servicio_id', $otServicio->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    
                    $remaining = max(0, (int)$item->planeado - (int)$item->completado);
                    $reqQty = (int)($i['cantidad'] ?? 0);
                    $addQty = min($reqQty, $remaining);
                    
                    if ($addQty <= 0) {
                        $cantAplicada[(int)$item->id] = 0;
                        continue;
                    }
                    
                    $cantAplicada[(int)$item->id] = $addQty;
                    $totalCantidadRegistrada += $addQty;
                    
                    // Actualizar completado
                    $item->completado += $addQty;
                    $item->save();
                }
                
                // Determinar precio unitario aplicado según tarifa
                $precioAplicado = null;
                if ($tipoTarifa !== 'NORMAL' && $precioManual > 0) {
                    // Tarifa EXTRA o FIN_DE_SEMANA: usar precio manual
                    $precioAplicado = $precioManual;
                } else {
                    // Tarifa NORMAL: intentar obtener precio del catálogo
                    // Para multi-servicio podríamos usar el precio_unitario del servicio o del item
                    $precioAplicado = (float)$otServicio->precio_unitario;
                    
                    // Si el servicio no tiene precio, intentar con el primer item que tenga precio
                    if ($precioAplicado <= 0) {
                        $itemConPrecio = $otServicio->items()->where('precio_unitario', '>', 0)->first();
                        if ($itemConPrecio) {
                            $precioAplicado = (float)$itemConPrecio->precio_unitario;
                        }
                    }
                }
                
                Log::info('RegistrarAvance Multi-Servicio: precio calculado', [
                    'orden_id' => $orden->id,
                    'servicio_id' => $otServicio->id,
                    'tarifa' => $tipoTarifa,
                    'precio_manual' => $precioManual,
                    'precio_aplicado' => $precioAplicado,
                    'cantidad_registrada' => $totalCantidadRegistrada,
                ]);
                
                // Registrar el avance en ot_servicio_avances
                if ($totalCantidadRegistrada > 0) {
                    // Obtener request_id del frontend (para idempotencia)
                    $requestId = $req->input('_request_id');
                    
                    if (empty($requestId)) {
                        // Si no viene request_id, generar uno (fallback)
                        $requestId = uniqid("{$otServicio->id}_", true);
                        Log::warning('⚠️ Request sin _request_id, generando uno', [
                            'invocation_id' => $invocationId,
                            'generated_request_id' => $requestId,
                        ]);
                    }
                    
                    Log::info('🔥 JUSTO ANTES de firstOrCreate()', [
                        'invocation_id' => $invocationId,
                        'request_id' => $requestId,
                        'servicio_id' => $otServicio->id,
                        'tarifa' => $tipoTarifa,
                        'cantidad' => $totalCantidadRegistrada,
                        'timestamp' => now()->format('Y-m-d H:i:s.u'),
                    ]);
                    
                    // Usar firstOrCreate para idempotencia
                    // Si el request_id ya existe, devuelve el existente sin duplicar
                    $avanceCreado = \App\Models\OTServicioAvance::firstOrCreate(
                        [
                            'ot_servicio_id' => $otServicio->id,
                            'request_id' => $requestId,
                        ],
                        [
                            'tarifa' => $tipoTarifa,
                            'precio_unitario_aplicado' => $precioAplicado,
                            'cantidad_registrada' => $totalCantidadRegistrada,
                            'comentario' => $comentarioFinal,
                            'created_by' => Auth::id(),
                        ]
                    );
                    
                    $wasRecentlyCreated = $avanceCreado->wasRecentlyCreated;
                    
                    Log::info('✅ Avance procesado', [
                        'invocation_id' => $invocationId,
                        'request_id' => $requestId,
                        'avance_id' => $avanceCreado->id,
                        'was_recently_created' => $wasRecentlyCreated,
                        'servicio_id' => $otServicio->id,
                        'tarifa' => $tipoTarifa,
                        'cantidad' => $totalCantidadRegistrada,
                        'precio' => $precioAplicado,
                        'timestamp' => now()->format('Y-m-d H:i:s.u'),
                    ]);
                    
                    if (!$wasRecentlyCreated) {
                        Log::info('ℹ️ Request duplicado detectado por request_id - devolviendo existente', [
                            'invocation_id' => $invocationId,
                            'request_id' => $requestId,
                            'avance_id' => $avanceCreado->id,
                        ]);
                    }
                    
                    // Recalcular subtotal del servicio sumando TODOS los avances (similar a segmentos tradicionales)
                    $todosAvances = \App\Models\OTServicioAvance::where('ot_servicio_id', $otServicio->id)->get();
                    $cantidadTotal = $todosAvances->sum('cantidad_registrada');
                    $subtotalTotal = 0;
                    
                    foreach ($todosAvances as $av) {
                        $cantidad = (int)$av->cantidad_registrada;
                        $precio = (float)$av->precio_unitario_aplicado;
                        $subtotalTotal += round($precio * $cantidad, 2);
                    }
                    
                    Log::info('Recálculo subtotal multi-servicio', [
                        'servicio_id' => $otServicio->id,
                        'avances_count' => $todosAvances->count(),
                        'cantidad_total' => $cantidadTotal,
                        'subtotal_calculado' => $subtotalTotal,
                        'subtotal_anterior' => $otServicio->subtotal,
                    ]);
                    
                    // Actualizar servicio con subtotal recalculado
                    // NO tocar precio_unitario original - mantener precio del catálogo
                    $otServicio->subtotal = $subtotalTotal;
                    $otServicio->save();
                    
                    Log::info('Subtotal servicio actualizado', [
                        'servicio_id' => $otServicio->id,
                        'nuevo_subtotal' => $otServicio->subtotal,
                    ]);
                    
                    // Recalcular totales de la OT completa
                    $subtotalOT = \App\Models\OTServicio::where('ot_id', $orden->id)->sum('subtotal');
                    $ivaOT = round($subtotalOT * 0.16, 2);
                    $totalOT = $subtotalOT + $ivaOT;
                    
                    $orden->subtotal = $subtotalOT;
                    $orden->iva = $ivaOT;
                    $orden->total = $totalOT;
                    $orden->total_real = $subtotalOT;
                    
                    Log::info('Totales OT actualizados', [
                        'orden_id' => $orden->id,
                        'subtotal_ot' => $subtotalOT,
                        'iva' => $ivaOT,
                        'total' => $totalOT,
                    ]);
                }
                
                // Verificar si TODOS los servicios de la OT están completados
                $todosServiciosCompletos = true;
                foreach ($orden->otServicios as $servicio) {
                    $itemsIncompletos = $servicio->items()->where('completado', '<', DB::raw('planeado'))->count();
                    if ($itemsIncompletos > 0) {
                        $todosServiciosCompletos = false;
                        break;
                    }
                }
                
                if ($todosServiciosCompletos && $orden->estatus !== 'completada') {
                    $orden->estatus = 'completada';
                    $orden->calidad_resultado = 'pendiente';
                    if (Schema::hasColumn('ordenes_trabajo', 'fecha_completada')) {
                        $orden->fecha_completada = now();
                    }
                    $justCompleted = true;
                }
                $orden->save();
                
            } else {
                // Lógica tradicional con orden_items
                foreach ($data['items'] as $i) {
                    $item = \App\Models\OrdenItem::where('id', $i['id_item'])
                        ->where('id_orden', $orden->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                // Cargar/asegurar segmentos existentes bajo lock
                $segQ = OrdenItemProduccionSegmento::where('id_orden', $orden->id)
                    ->where('id_item', $item->id);
                $segmentos = $segQ->lockForUpdate()->get();

                // Backfill: si ya hay producción legacy pero aún no hay segmentos, crear un segmento NORMAL inicial
                if ($segmentos->isEmpty() && (int)($item->cantidad_real ?? 0) > 0) {
                    $qtyHist = (int)$item->cantidad_real;
                    $puHist = (float)$item->precio_unitario;
                    if ($puHist <= 0) {
                        $puHist = (float)$pricing->precioUnitario($orden->id_centrotrabajo, $orden->id_servicio, $item->tamano);
                    }
                    if ($puHist <= 0) {
                        $baseQty = $qtyHist > 0 ? $qtyHist : max(1, (int)$item->cantidad_planeada);
                        $puHist = (float)$item->subtotal / max(1, $baseQty);
                    }
                    if ($puHist > 0 && $qtyHist > 0) {
                        OrdenItemProduccionSegmento::create([
                            'id_orden' => $orden->id,
                            'id_item' => $item->id,
                            'id_usuario' => Auth::id(),
                            'tipo_tarifa' => 'NORMAL',
                            'cantidad' => $qtyHist,
                            'precio_unitario' => $puHist,
                            'subtotal' => round($puHist * $qtyHist, 2),
                            'nota' => 'Backfill automático (producción previa)',
                        ]);
                        $segmentos = $segQ->lockForUpdate()->get();
                    }
                }

                $qtySeg = (int)$segmentos->sum('cantidad');
                $subSeg = (float)$segmentos->sum('subtotal');
                $remaining = max(0, (int)$item->cantidad_planeada - $qtySeg);
                $reqQty = (int)($i['cantidad'] ?? 0);
                $addQty = min($reqQty, $remaining);
                if ($addQty <= 0) {
                    $cantAplicada[(int)$item->id] = 0;
                    continue;
                }
                $cantAplicada[(int)$item->id] = $addQty;

                // PU del segmento según tarifa
                $puSeg = 0.0;
                if ($tipoTarifa === 'NORMAL') {
                    $puSeg = (float)$pricing->precioUnitario($orden->id_centrotrabajo, $orden->id_servicio, $item->tamano);
                    if ($puSeg <= 0) {
                        // fallback robusto
                        $puSeg = (float)$item->precio_unitario;
                    }
                    if ($puSeg <= 0) {
                        $baseQty = $qtySeg > 0 ? $qtySeg : max(1, (int)$item->cantidad_planeada);
                        $puSeg = (float)$item->subtotal / max(1, $baseQty);
                    }
                } else {
                    $puSeg = (float)$precioManual;
                }
                if ($puSeg <= 0) {
                    throw new \RuntimeException('No se pudo determinar un precio unitario para el segmento.');
                }

                $subSegNew = round($puSeg * $addQty, 2);
                OrdenItemProduccionSegmento::create([
                    'id_orden' => $orden->id,
                    'id_item' => $item->id,
                    'id_usuario' => Auth::id(),
                    'tipo_tarifa' => $tipoTarifa,
                    'cantidad' => $addQty,
                    'precio_unitario' => $puSeg,
                    'subtotal' => $subSegNew,
                    'nota' => $comentarioFinal ?: null,
                ]);

                // Actualizar agregados del item (cantidad_real, subtotal, precio_unitario promedio)
                $qtyTotal = $qtySeg + $addQty;
                $subTotal = $subSeg + $subSegNew;
                $item->cantidad_real = $qtyTotal;
                $item->subtotal = $subTotal;
                $item->precio_unitario = $qtyTotal > 0 ? ($subTotal / $qtyTotal) : (float)($item->precio_unitario ?? 0);
                $item->save();
            }

            // totales y estatus (solo para modo tradicional)
            $sumReal = $orden->items()->sum('cantidad_real');
            $sumPlan = $orden->items()->sum('cantidad_planeada');

            // Monetario real: usar la suma de subtotales (robusto incluso si algún PU era 0 y se corrigió)
            $orden->total_real = (float)$orden->items()->selectRaw('COALESCE(SUM(subtotal),0) as t')->value('t');
            // También reflejar estos importes en subtotal/iva/total para facturación basada en lo realizado
            $orden->subtotal = $orden->total_real;
            $orden->iva = $orden->subtotal * 0.16;
            $orden->total = $orden->subtotal + $orden->iva;


            $justCompleted = ($orden->estatus !== 'completada') && ($sumReal >= $sumPlan && $sumPlan > 0);
            if ($justCompleted) {
                $orden->estatus = 'completada';
                // Cuando la OT se completa de nuevo, reiniciar el marcador de calidad a 'pendiente'
                $orden->calidad_resultado = 'pendiente';
                // Persistir fecha de completado para estabilidad en reportes y PDFs
                if (Schema::hasColumn('ordenes_trabajo', 'fecha_completada')) {
                    $orden->fecha_completada = now();
                }
                $orden->save();

                // Notificar a calidad del centro con notificación específica
                // Buscar usuarios con rol 'calidad' que tengan asignado este centro
                // Ya sea como centro principal O en centros adicionales
                $usuariosCalidad = User::role('calidad')
                    ->where(function($query) use ($orden) {
                        $query->where('centro_trabajo_id', $orden->id_centrotrabajo)
                              ->orWhereHas('centros', function($q) use ($orden) {
                                  $q->where('centro_trabajo_id', $orden->id_centrotrabajo);
                              });
                    })
                    ->get();
                
                if ($usuariosCalidad->isNotEmpty()) {
                    try {
                        Notification::send($usuariosCalidad, new OtListaParaCalidad($orden));
                    } catch (\Throwable $e) {
                        Log::warning('RegistrarAvance: fallo al notificar calidad (ignorado)', [
                            'orden_id' => $orden->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } else {
                $orden->save();
            }
            } // Fin del else (lógica tradicional)

            // Registrar avances en la tabla de avances
            // SOLO para OTs tradicionales (no multi-servicio)
            // Para multi-servicio se usa ot_servicio_avances más abajo
            if (!$esMultiServicio) {
                // Marcar como corregido SOLO si:
                // 1. Existe un rechazo de calidad registrado para esta orden
                // 2. Y ese rechazo fue DESPUÉS de que ya había avances (es decir, es una corrección real)
                $rechazoCalidad = \App\Models\Aprobacion::where('aprobable_type', \App\Models\Orden::class)
                    ->where('aprobable_id', $orden->id)
                    ->where('tipo', 'calidad')
                    ->where('resultado', 'rechazado')
                    ->latest()
                    ->first();
                
                // Solo es corregido si hay un rechazo Y había avances antes de ese rechazo
                $isCorregido = false;
                if ($rechazoCalidad) {
                    $avancesAntesDeRechazo = \App\Models\Avance::where('id_orden', $orden->id)
                        ->where('created_at', '<', $rechazoCalidad->created_at)
                        ->exists();
                    $isCorregido = $avancesAntesDeRechazo;
                }
                
                foreach ($data['items'] as $d) {
                    $aplicada = (int)($cantAplicada[(int)$d['id_item']] ?? 0);
                    if ($aplicada > 0) {
                        \App\Models\Avance::create([
                            'id_orden' => $orden->id,
                            'id_item' => $d['id_item'],
                            'id_usuario' => Auth::id(),
                            'cantidad' => $aplicada,
                            'comentario' => $comentarioFinal ?: null,
                            'es_corregido' => $isCorregido,
                        ]);
                    }
                }
            }

            // NOTA: El registro de avances multi-servicio ya se hizo más arriba con firstOrCreate()
            // (líneas 675-776) para garantizar idempotencia. No duplicar aquí.

            // Registrar en activity log
            $this->act('ordenes')
                ->performedOn($orden)
                ->event('avance')
                ->withProperties(['items' => $data['items'], 'comentario' => $req->comentario])
                ->log("OT #{$orden->id}: avance registrado");
        });
        } catch (\Throwable $e) {
            Log::error('RegistrarAvance: excepción', [
                'orden_id' => $orden->id,
                'user_id'  => optional($req->user())->id,
                'message'  => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
                'trace'    => collect(explode("\n", $e->getTraceAsString()))->take(15)->all(),
            ]);
            return back()->withErrors(['items' => 'No se pudo registrar el avance. Inténtalo de nuevo y si persiste, contacta soporte.']);
        }

        // Encolar PDF si se completó
        if ($justCompleted) {
            GenerateOrdenPdf::dispatch($orden->id);
        }

        return back()->with('ok','Avance registrado');
    }

    /** Registrar faltantes (disminuir cantidad planeada y dejar evidencia) */
    public function registrarFaltantes(Request $req, Orden $orden)
    {
        $this->authorize('reportarAvance', $orden);
        $this->authorizeFromCentro($orden->id_centrotrabajo, $orden);

        if ($this->ordenBloqueadaParaEdicionProduccion($orden)) {
            return back()->withErrors(['orden' => 'La OT está facturada o bloqueada; ya no se permite editar producción.']);
        }

        $data = $req->validate([
            'items'                   => ['required','array','min:1'],
            'items.*.id_item'         => ['required','integer','exists:orden_items,id'],
            'items.*.faltantes'       => ['required','integer','min:0'],
            'nota'                    => ['nullable','string','max:2000'],
        ]);

        $resumen = [];

    DB::transaction(function () use ($orden, $data, &$resumen) {
            foreach ($data['items'] as $d) {
                $item = \App\Models\OrdenItem::where('id', $d['id_item'])
                    ->where('id_orden', $orden->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $falt = max(0, (int)$d['faltantes']);
                $pend = max(0, (int)$item->cantidad_planeada - (int)$item->cantidad_real);
                if ($falt <= 0) continue; // nada que hacer
                if ($falt > $pend) {
                    // Limitar a lo pendiente para preservar coherencia
                    $falt = $pend;
                }

                if ($falt > 0) {
                    // Si hay segmentos, NO recalcular subtotal por PU; el subtotal debe venir de la suma de segmentos
                    $segmentos = OrdenItemProduccionSegmento::where('id_orden', $orden->id)
                        ->where('id_item', $item->id)
                        ->lockForUpdate()
                        ->get();

                    $tieneSegmentos = $segmentos->isNotEmpty();
                    $puEfectivo = (float)$item->precio_unitario;
                    if (!$tieneSegmentos) {
                        $baseQty = ((int)$item->cantidad_real > 0) ? (int)$item->cantidad_real : max(1, (int)$item->cantidad_planeada);
                        if ($puEfectivo <= 0) {
                            $puEfectivo = (float)$item->subtotal / max(1, $baseQty);
                        }
                    }

                    // Acumular registro de faltantes y ajustar plan
                    $nuevoPlan = (int)$item->cantidad_planeada - $falt;
                    // Nunca por debajo de lo ya realizado
                    if ($nuevoPlan < (int)$item->cantidad_real) {
                        $nuevoPlan = (int)$item->cantidad_real;
                    }
                    // Acumula faltantes (nuevo campo)
                    $item->faltantes = (int)($item->faltantes ?? 0) + (int)$falt;
                    $item->cantidad_planeada = $nuevoPlan;
                    // Asegurar PU y subtotal coherentes con lo REAL producido
                    if ($tieneSegmentos) {
                        $qtySeg = (int)$segmentos->sum('cantidad');
                        $subSeg = (float)$segmentos->sum('subtotal');
                        $item->cantidad_real = $qtySeg;
                        $item->subtotal = $subSeg;
                        $item->precio_unitario = $qtySeg > 0 ? ($subSeg / $qtySeg) : (float)($item->precio_unitario ?? 0);
                    } else {
                        if ($item->precio_unitario <= 0 && $puEfectivo > 0) {
                            $item->precio_unitario = $puEfectivo;
                        }
                        $item->subtotal = $puEfectivo * (int)$item->cantidad_real;
                    }
                    $item->save();

                    $resumen[] = [
                        'id_item' => $item->id,
                        'descripcion' => $item->tamano ?: ($item->descripcion ?: 'Item'),
                        'faltantes' => $d['faltantes'],
                    ];
                }
            }

            // Recalcular totales monetarios con base en los subtotales de cada item (robusto aunque PU sea 0)
            $sumSubtotales = (float)$orden->items()->selectRaw('COALESCE(SUM(subtotal),0) as t')->value('t');
            $orden->total_real = $sumSubtotales; // total_real debe representar el importe real
            // Para facturar lo realmente realizado, el subtotal/iva/total deben reflejar lo real
            $orden->subtotal = $sumSubtotales;
            $orden->iva      = $sumSubtotales * 0.16;
            $orden->total    = $orden->subtotal + $orden->iva;

            // Si con el nuevo plan la suma real alcanza el plan, marcar completada
            $sumReal = (int)$orden->items()->sum('cantidad_real');
            $sumPlan = (int)$orden->items()->sum('cantidad_planeada');
            if ($sumPlan > 0 && $sumReal >= $sumPlan) {
                if ($orden->estatus !== 'completada') {
                    $orden->estatus = 'completada';
                    $orden->calidad_resultado = 'pendiente';
                    if (Schema::hasColumn('ordenes_trabajo', 'fecha_completada')) {
                        $orden->fecha_completada = now();
                    }
                }
            }
            $orden->save();

            // Registrar un avance informativo con resumen
            if (!empty($resumen)) {
                $partes = array_map(function($r){
                    return (string)($r['faltantes']).' en '.($r['descripcion']);
                }, $resumen);
                $coment = '[FALTANTES] '.implode('; ', $partes);
                if (!empty($data['nota'])) { $coment .= ' | Nota: '.$data['nota']; }
                \App\Models\Avance::create([
                    'id_orden' => $orden->id,
                    'id_item' => null,
                    'id_usuario' => Auth::id(),
                    'cantidad' => 0,
                    'comentario' => $coment,
                    'es_corregido' => 0,
                ]);
            }

            // Activity log
            $this->act('ordenes')
                ->performedOn($orden)
                ->event('faltantes')
                ->withProperties(['items' => $resumen, 'nota' => $data['nota'] ?? null])
                ->log("OT #{$orden->id}: faltantes aplicados");
        });

        return back()->with('ok','Faltantes aplicados');
    }

    /** Detalle de OT */
    public function show(Orden $orden)
    {
        $this->authorize('view', $orden);
        $this->authorizeFromCentro($orden->id_centrotrabajo, $orden);

        $orden->load([
            'solicitud.archivos','solicitud.centroCosto','solicitud.marca','solicitud.tamanos',
            'servicio','centro','area','items','teamLeader',
            'items.segmentosProduccion' => fn($q) => $q->with('usuario')->orderBy('created_at'),
            'avances' => fn($q) => $q->with(['usuario', 'item'])->orderByDesc('created_at'),
            'evidencias' => fn($q)=>$q->with('usuario')->orderByDesc('id'),
            'otServicios.servicio',
            'otServicios.items',
            'otServicios.avances' => fn($q) => $q->with('createdBy')->orderBy('created_at'),
            'otServicios.addedBy', // Cargar usuario que agregó servicio adicional
        ]);

        // Flag per-centro: servicio con tamaños SI existe configuración de tamaños en el centro y aún no hay desglose capturado
        $usaTamanosCentro = \App\Models\ServicioCentro::where('id_centrotrabajo',$orden->id_centrotrabajo)
            ->where('id_servicio',$orden->id_servicio)
            ->whereHas('tamanos')
            ->exists();
        $pendienteTamanos = $usaTamanosCentro && ($orden->solicitud && $orden->solicitud->tamanos->count() === 0);

        // Obtener usuario autenticado ANTES de usarlo en cualquier condición/log
        $authUser = Auth::user();
        $canReportar = Gate::allows('reportarAvance', $orden);
        // Diagnóstico adicional: si es Team Leader y no puede reportar, registrar contexto
        if (!$canReportar && $authUser && $authUser->hasRole('team_leader')) {
            Log::info('TL sin permiso reportarAvance', [
                'orden_id' => $orden->id,
                'orden_team_leader_id' => (int)$orden->team_leader_id,
                'user_id' => (int)$authUser->id,
                'user_roles' => $authUser->roles->pluck('name')->all(),
            ]);
        }
        $isAdminOrCoord = false;
        if ($authUser instanceof \App\Models\User) {
            $isAdminOrCoord = $authUser->hasAnyRole(['admin','coordinador']);
        }
        $canAsignar  = $isAdminOrCoord && $orden->estatus !== 'completada';

        // Inicializar variable para verificar si todos los servicios están completos
        $todosServiciosCompletos = false;

        // Permisos específicos adicionales
        $canCalidad = false; $canClienteAutorizar = false; $canFacturar = false;
        if ($authUser instanceof \App\Models\User) {
            // Calidad: admin o rol calidad con centro permitido (pivot + principal)
            // NUEVA REGLA: Habilitar cuando todos los servicios estén al 100% (progreso = 100)
            // O cuando la OT esté completada (para compatibilidad con OTs no multi-servicio)
            $progresoCompleto = $todosServiciosCompletos || $orden->estatus === 'completada';
            
            if ($progresoCompleto && $orden->calidad_resultado === 'pendiente') {
                if ($authUser->hasRole('admin')) {
                    $canCalidad = true;
                } elseif ($authUser->hasRole('calidad')) {
                    $idsPermitidos = $this->allowedCentroIds($authUser);
                    $canCalidad = in_array((int)$orden->id_centrotrabajo, array_map('intval', $idsPermitidos), true);
                }
                // Regla de negocio: si es un servicio por tamaños y falta desglose, NO permitir validar calidad
                if ($pendienteTamanos) { $canCalidad = false; }
            }

            // Cliente autoriza: dueño de la solicitud (o admin) y calidad validada
            $esDueno = ($orden->solicitud && (int)$orden->solicitud->id_cliente === (int)$authUser->id);
            $canClienteAutorizar = ($authUser->hasRole('admin') || $esDueno)
                && $orden->calidad_resultado === 'validado'
                && $orden->estatus === 'completada';
            // Motivos de bloqueo para diagnóstico front-end
            $bloqueosCliente = [];
            if (!$esDueno && !$authUser->hasRole('admin')) { $bloqueosCliente[] = 'No eres el dueño de la solicitud'; }
            if ($orden->calidad_resultado !== 'validado') { $bloqueosCliente[] = 'Calidad aún no valida la OT'; }
            if ($orden->estatus !== 'completada') { $bloqueosCliente[] = 'La OT no está completada'; }

            // Facturar: rol facturacion o admin; mantener restricción de estatus
            // Nota: el acceso al centro ya se valida en authorizeFromCentro (facturacion tiene bypass)
            $canFacturar = ($authUser->hasAnyRole(['admin','facturacion']))
                && $orden->estatus === 'autorizada_cliente';
        }

        $teamLeaders = $canAsignar
            ? User::role('team_leader')
                ->where('centro_trabajo_id',$orden->id_centrotrabajo)
                ->select('id','name')->orderBy('name')->get()
            : [];

        // Cotización basada en items (usa cantidades reales si existen, si no, planeadas)
        $ivaRate = 0.16;
        $lines = [];
        $sub = 0.0;
        $usaSegmentos = false;
        
        // Si es OT multi-servicio, usar los servicios directamente
        $esMultiServicio = $orden->otServicios()->exists();
        
        if ($esMultiServicio) {
            // Para multi-servicio, usar subtotales de servicios
            $sub = (float)$orden->otServicios->sum('subtotal');
            foreach ($orden->otServicios as $serv) {
                $lines[] = [
                    'label'    => $serv->servicio->nombre ?? 'Servicio',
                    'cantidad' => (int)$serv->cantidad,
                    'pu'       => $serv->cantidad > 0 ? ($serv->subtotal / $serv->cantidad) : 0,
                    'subtotal' => (float)$serv->subtotal,
                ];
            }
        } else {
            // Para OT tradicional, usar items
            foreach ($orden->items as $it) {
                $qty = ($it->cantidad_real ?? 0) > 0 ? (int)$it->cantidad_real : (int)$it->cantidad_planeada;
                $tieneSeg = ($it->segmentosProduccion ?? null) && $it->segmentosProduccion->count() > 0;
                $lineSub = ($tieneSeg && (int)($it->cantidad_real ?? 0) > 0)
                    ? (float)($it->subtotal ?? 0)
                    : ((float)$it->precio_unitario * $qty);
                if ($tieneSeg) { $usaSegmentos = true; }
                $sub += $lineSub;
                $lines[] = [
                    'label'    => $it->tamano ? ('Tamaño: '.ucfirst($it->tamano)) : ($it->descripcion ?: 'Item'),
                    'cantidad' => $qty,
                    'pu'       => ($tieneSeg && $qty > 0 && (int)($it->cantidad_real ?? 0) > 0)
                        ? (float)($lineSub / max(1, $qty))
                        : (float)$it->precio_unitario,
                    'subtotal' => $lineSub,
                ];
            }
        }
        
        $cot = [
            'lines' => $lines,
            'subtotal' => $sub,
            'iva_rate' => $ivaRate,
            'iva' => $sub * $ivaRate,
            'total' => $sub * (1 + $ivaRate),
            'calc_mode' => $esMultiServicio ? 'MULTI_SERVICIO' : ($usaSegmentos ? 'SEGMENTOS' : 'FIJO'),
        ];

        // Precios unitarios por tamaño para vista de desglose (flujo diferido)
        $preciosTamaño = null;
    if ($usaTamanosCentro) {
            $pricing = app(\App\Domain\Servicios\PricingService::class);
            $preciosTamaño = [
                'chico'   => (float)$pricing->precioUnitario($orden->id_centrotrabajo, $orden->id_servicio, 'chico'),
                'mediano' => (float)$pricing->precioUnitario($orden->id_centrotrabajo, $orden->id_servicio, 'mediano'),
                'grande'  => (float)$pricing->precioUnitario($orden->id_centrotrabajo, $orden->id_servicio, 'grande'),
                'jumbo'   => (float)$pricing->precioUnitario($orden->id_centrotrabajo, $orden->id_servicio, 'jumbo'),
            ];
        }

        // Resumen de unidades: planeado original, completado, faltante y total vigente
        $planeadoOriginal = (int)$orden->items->sum(fn($i)=> (int)$i->cantidad_planeada + (int)($i->faltantes ?? 0));
        $completadoSum    = (int)$orden->items->sum('cantidad_real');
        $faltanteSum      = (int)$orden->items->sum(function($i){ return (int)($i->faltantes ?? 0); });
        $totalVigente     = (int)$orden->items->sum('cantidad_planeada');

        // Información de servicios multi-servicio con tamaños
        $serviciosConTamanos = [];
        $preciosPorServicio = [];
        
        if ($orden->otServicios()->exists()) {
            $pricing = app(\App\Domain\Servicios\PricingService::class);
            
            \Log::info('=== DEBUG SERVICIOS CON TAMAÑOS ===', [
                'orden_id' => $orden->id,
                'centro_trabajo' => $orden->id_centrotrabajo,
                'total_servicios' => $orden->otServicios->count(),
            ]);
            
            foreach ($orden->otServicios as $otServicio) {
                \Log::info('Procesando servicio', [
                    'ot_servicio_id' => $otServicio->id,
                    'servicio_id' => $otServicio->servicio_id,
                    'cantidad' => $otServicio->cantidad,
                ]);
                
                $usaTamanos = \App\Models\ServicioCentro::where('id_centrotrabajo', $orden->id_centrotrabajo)
                    ->where('id_servicio', $otServicio->servicio_id)
                    ->whereHas('tamanos')
                    ->exists();
                
                \Log::info('Verificación de tamaños', [
                    'servicio_id' => $otServicio->servicio_id,
                    'usa_tamanos' => $usaTamanos,
                ]);
                
                // Verificar si tiene items con tamaños ya definidos
                // Si tiene items pero todos tienen tamano=null, entonces está pendiente
                $totalItems = $otServicio->items()->count();
                $itemsConTamano = $otServicio->items()->whereNotNull('tamano')->count();
                $tieneTamanosDefinidos = $totalItems > 0 && $itemsConTamano > 0;
                
                \Log::info('Items del servicio', [
                    'total_items' => $totalItems,
                    'items_con_tamano' => $itemsConTamano,
                    'tiene_tamanos_definidos' => $tieneTamanosDefinidos,
                ]);
                
                if ($usaTamanos) {
                    $serviciosConTamanos[$otServicio->id] = [
                        'usa_tamanos' => true,
                        'pendiente_definir' => !$tieneTamanosDefinidos,
                        'cantidad_total' => $otServicio->cantidad,
                    ];
                    
                    // Obtener precios por tamaño para este servicio
                    $preciosPorServicio[$otServicio->id] = [
                        'chico'   => (float)$pricing->precioUnitario($orden->id_centrotrabajo, $otServicio->servicio_id, 'chico'),
                        'mediano' => (float)$pricing->precioUnitario($orden->id_centrotrabajo, $otServicio->servicio_id, 'mediano'),
                        'grande'  => (float)$pricing->precioUnitario($orden->id_centrotrabajo, $otServicio->servicio_id, 'grande'),
                        'jumbo'   => (float)$pricing->precioUnitario($orden->id_centrotrabajo, $otServicio->servicio_id, 'jumbo'),
                    ];
                    
                    \Log::info('Servicio con tamaños agregado', [
                        'ot_servicio_id' => $otServicio->id,
                        'info' => $serviciosConTamanos[$otServicio->id],
                    ]);
                }
            }
            
            \Log::info('=== RESULTADO FINAL ===', [
                'servicios_con_tamanos' => $serviciosConTamanos,
                'count' => count($serviciosConTamanos),
            ]);
        }

        // Preparar datos de servicios con totales calculados (para multi-servicio)
        $todosServiciosCompletos = false;
        if ($orden->otServicios()->exists()) {
            $serviciosData = $orden->otServicios->map(function ($otServicio) {
                $totales = $otServicio->calcularTotales();
                
                return [
                    'id' => $otServicio->id,
                    'servicio' => $otServicio->servicio->only(['id', 'nombre']),
                    'tipo_cobro' => $otServicio->tipo_cobro,
                    'cantidad' => $otServicio->cantidad,
                    'precio_unitario' => $otServicio->precio_unitario,
                    'subtotal' => $otServicio->subtotal,
                    'planeado' => $totales['planeado'],
                    'completado' => $totales['completado'],
                    'faltantes_registrados' => $totales['faltantes_registrados'],
                    'pendiente' => $totales['pendiente'],
                    'progreso' => $totales['progreso'],
                    'total' => $totales['total'],
                    'items' => $otServicio->items->map(function ($item) {
                        $planeado = (int)$item->planeado;
                        $completado = (int)$item->completado;
                        $faltantesRegistrados = (int)$item->faltante;
                        $pendiente = max(0, $planeado - ($completado + $faltantesRegistrados));
                        $progreso = $planeado > 0 
                            ? round((($completado + $faltantesRegistrados) / $planeado) * 100) 
                            : 0;
                        
                        return [
                            'id' => $item->id,
                            'descripcion_item' => $item->descripcion_item,
                            'planeado' => $planeado,
                            'completado' => $completado,
                            'faltantes_registrados' => $faltantesRegistrados,
                            'pendiente' => $pendiente,
                            'progreso' => $progreso,
                        ];
                    })->toArray(),
                    'avances' => $otServicio->avances->map(function ($avance) {
                        return [
                            'id' => $avance->id,
                            'tarifa' => $avance->tarifa,
                            'precio_unitario_aplicado' => $avance->precio_unitario_aplicado,
                            'cantidad_registrada' => $avance->cantidad_registrada,
                            'comentario' => $avance->comentario,
                            'created_by' => $avance->createdBy->name ?? 'N/A',
                            'created_at' => $avance->created_at->format('Y-m-d H:i'),
                        ];
                    })->toArray(),
                ];
            });
            
            // Reemplazar la relación otServicios en el modelo orden con los datos enriquecidos
            $orden->setRelation('otServicios', $serviciosData);
            
            // Verificar si TODOS los servicios tienen progreso = 100%
            $todosServiciosCompletos = $serviciosData->every(function ($servicio) {
                return $servicio['progreso'] >= 100;
            });
            
            // Si todos los servicios están completos Y la orden NO ha avanzado más allá de 'completada', actualizarla
            // No sobrescribir estatus más avanzados: autorizada_cliente, facturada, etc.
            $estatusQueNoSobrescribir = ['autorizada_cliente', 'facturada', 'entregada'];
            if ($todosServiciosCompletos && !in_array($orden->estatus, $estatusQueNoSobrescribir) && $orden->estatus !== 'completada') {
                $orden->estatus = 'completada';
                $orden->save();
                \Log::info("Orden {$orden->id} actualizada a COMPLETADA automáticamente (todos los servicios al 100%)");
            }
        }

        return Inertia::render('Ordenes/Show', [
            'orden'       => $orden,
            'can'         => [
                'reportarAvance'     => $canReportar,
                'asignar_tl'         => $canAsignar,
                'calidad_validar'    => $canCalidad,
                'cliente_autorizar'  => $canClienteAutorizar,
                'facturar'           => $canFacturar,
                'definir_tamanos'    => Gate::allows('definirTamanos', $orden),
                'agregar_servicio_adicional' => $authUser && $authUser->hasAnyRole(['admin', 'coordinador', 'team_leader']),
            ],
            'debug' => [
                'orden_team_leader_id' => (int)$orden->team_leader_id,
                'auth_user_id' => (int)($authUser?->id ?? 0),
                'auth_roles' => $authUser?->roles?->pluck('name')?->all() ?? [],
            ],
            'bloqueos_cliente_autorizar' => $bloqueosCliente ?? [],
            'teamLeaders' => $teamLeaders,
            'cotizacion'  => $cot,
            'unidades'    => [
                'planeado'   => $planeadoOriginal,
                'completado' => $completadoSum,
                'faltante'   => $faltanteSum,
                'total'      => $totalVigente,
            ],
            'urls'        => [
                'asignar_tl'        => route('ordenes.asignarTL', $orden),
                'avances_store'     => route('ordenes.avances.store', $orden),
                'faltantes_store'   => route('ordenes.faltantes.store', $orden),
                'segmentos_update'  => route('ordenes.segmentos.update', ['orden' => $orden->id, 'segmento' => 0]),
                'calidad_page'      => route('calidad.show', $orden),
                'calidad_validar'   => route('calidad.validar', $orden),
                'calidad_rechazar'  => route('calidad.rechazar', $orden),
                'cliente_autorizar' => route('cliente.autorizar', $orden),
                'facturar'          => route('facturas.createFromOrden', $orden),
                'pdf'               => route('ordenes.pdf', $orden),
                'excel_origen'      => ($orden->solicitud && $orden->solicitud->archivo_excel_stored_name)
                    ? route('ordenes.excel.origen', $orden)
                    : null,
                'evidencias_store'  => route('evidencias.store', $orden),
                'evidencias_destroy'=> route('evidencias.destroy', 0),
                'definir_tamanos'   => route('ordenes.definirTamanos', $orden),
                'agregar_servicio_adicional' => route('ordenes.agregarServicioAdicional', $orden),
            ],
            'flags' => [ 'pendiente_tamanos' => $pendienteTamanos ],
            'precios_tamano' => $preciosTamaño,
            'servicios_con_tamanos' => $serviciosConTamanos,
            'precios_por_servicio' => $preciosPorServicio,
            'servicios_disponibles' => $this->getServiciosDisponibles($orden->id_centrotrabajo),
        ]);
    }

    /** Definir desglose por tamaños para OT (flujo diferido) */
    public function definirTamanos(Request $req, Orden $orden)
    {
        $this->authorize('definirTamanos', $orden);
        $this->authorizeFromCentro($orden->id_centrotrabajo, $orden);

        $orden->load(['servicio','solicitud.tamanos','items']);
        if (!$orden->servicio || !(bool)$orden->servicio->usa_tamanos) {
            abort(422, 'La OT no corresponde a un servicio por tamaños.');
        }
        if ($orden->solicitud && $orden->solicitud->tamanos->count() > 0) {
            return back()->withErrors(['tamanos' => 'La solicitud ya tiene un desglose por tamaños.']);
        }
        // Permitir definir tamaños incluso si hubo avances previos; los avances quedarán desacoplados del ítem (fk null)

        $data = $req->validate([
            'chico'   => ['nullable','integer','min:0'],
            'mediano' => ['nullable','integer','min:0'],
            'grande'  => ['nullable','integer','min:0'],
            'jumbo'   => ['nullable','integer','min:0'],
        ]);

        $cantidades = [
            'chico'   => (int)($data['chico'] ?? 0),
            'mediano' => (int)($data['mediano'] ?? 0),
            'grande'  => (int)($data['grande'] ?? 0),
            'jumbo'   => (int)($data['jumbo'] ?? 0),
        ];
        $suma = array_sum($cantidades);
        // Objetivo dinámico: total vigente después de faltantes (suma de cantidad_planeada actual de los ítems)
        $totalVigente = (int)$orden->items->sum('cantidad_planeada');
        if ($suma !== $totalVigente) {
            return back()->withErrors(['tamanos' => "La suma ($suma) debe ser igual al total vigente ($totalVigente)."]);
        }

        DB::transaction(function () use ($orden, $cantidades) {
            // Reemplazar items en la OT
            $orden->items()->delete();

            $pricing = app(\App\Domain\Servicios\PricingService::class);
            $sub = 0.0;
            $wasCompleted = ($orden->estatus === 'completada');

            foreach ($cantidades as $tam => $qty) {
                if ($qty <= 0) continue;
                $pu = (float)$pricing->precioUnitario($orden->id_centrotrabajo, $orden->id_servicio, $tam);
                $lineSub = $pu * (int)$qty;

                \App\Models\OrdenItem::create([
                    'id_orden'          => $orden->id,
                    'descripcion'       => $orden->descripcion_general ?? 'Item',
                    'tamano'            => $tam,
                    'cantidad_planeada' => (int)$qty,
                    'cantidad_real'     => $wasCompleted ? (int)$qty : 0, // si la OT YA estaba completada, mantener; de lo contrario iniciar en 0
                    'precio_unitario'   => $pu,
                    'subtotal'          => $lineSub,
                ]);
                $sub += $lineSub;
            }

            // Actualizar totales en OT
            $ivaRate = 0.16; $iva = $sub * $ivaRate; $total = $sub + $iva;
            $orden->subtotal = $sub; $orden->iva = $iva; $orden->total = $total;
            // total_real como suma de subtotales (robusto ante PU vacíos)
            $orden->total_real = (float)$orden->items()->selectRaw('COALESCE(SUM(subtotal),0) as t')->value('t');
            $orden->save();

            // Persistir tamaños en la Solicitud y recalcular sus totales
            $orden->solicitud->tamanos()->delete();
            $payloadJson = [];
            $pricing2 = $pricing; $subSol = 0.0;
            foreach ($cantidades as $tam => $qty) {
                if ($qty <= 0) continue;
                \App\Models\SolicitudTamano::create([
                    'id_solicitud' => $orden->id_solicitud,
                    'tamano' => $tam,
                    'cantidad' => (int)$qty,
                ]);
                $payloadJson[] = ['tamano'=>$tam,'cantidad'=>(int)$qty];

                $pu = (float)$pricing2->precioUnitario($orden->id_centrotrabajo, $orden->id_servicio, $tam);
                $subSol += $pu * (int)$qty;
            }
            $orden->solicitud->tamanos_json = json_encode($payloadJson);
            $orden->solicitud->subtotal = $subSol;
            $orden->solicitud->iva = $subSol * 0.16;
            $orden->solicitud->total = $orden->solicitud->subtotal + $orden->solicitud->iva;
            $orden->solicitud->save();

            // Registrar actividad
            $this->act('ordenes')
                ->performedOn($orden)
                ->event('definir_tamanos')
                ->withProperties(['cantidades' => $cantidades])
                ->log("OT #{$orden->id}: tamaños definidos y precios calculados");
        });

        return back()->with('ok','Desglose por tamaños aplicado correctamente');
    }

    /** Definir tamaños para un servicio específico en OT multi-servicio */
    public function definirTamanosServicio(Request $req, Orden $orden, int $servicio)
    {
        $this->authorize('definirTamanos', $orden);
        $this->authorizeFromCentro($orden->id_centrotrabajo, $orden);

        // Buscar el servicio manualmente
        $servicio = \App\Models\OTServicio::findOrFail($servicio);
        
        // Verificar que el servicio pertenece a esta OT
        if ($servicio->ot_id !== $orden->id) {
            abort(422, 'El servicio no pertenece a esta OT.');
        }

        // Verificar que el servicio usa tamaños
        $usaTamanos = \App\Models\ServicioCentro::where('id_centrotrabajo', $orden->id_centrotrabajo)
            ->where('id_servicio', $servicio->servicio_id)
            ->whereHas('tamanos')
            ->exists();

        if (!$usaTamanos) {
            return back()->withErrors(['tamanos' => 'Este servicio no usa tamaños.']);
        }

        // Verificar si ya tiene items con tamaños definidos
        if ($servicio->items()->whereNotNull('tamano')->count() > 0) {
            return back()->withErrors(['tamanos' => 'Este servicio ya tiene tamaños definidos.']);
        }

        $data = $req->validate([
            'chico'   => ['nullable','integer','min:0'],
            'mediano' => ['nullable','integer','min:0'],
            'grande'  => ['nullable','integer','min:0'],
            'jumbo'   => ['nullable','integer','min:0'],
        ]);

        $cantidades = [
            'chico'   => (int)($data['chico'] ?? 0),
            'mediano' => (int)($data['mediano'] ?? 0),
            'grande'  => (int)($data['grande'] ?? 0),
            'jumbo'   => (int)($data['jumbo'] ?? 0),
        ];
        $suma = array_sum($cantidades);
        
        // La suma debe ser igual a la cantidad del servicio
        $totalServicio = (int)$servicio->cantidad;
        if ($suma !== $totalServicio) {
            return back()->withErrors(['tamanos' => "La suma ($suma) debe ser igual a la cantidad del servicio ($totalServicio)."]);
        }

        DB::transaction(function () use ($orden, $servicio, $cantidades) {
            // Reemplazar items del servicio
            $servicio->items()->delete();

            // Obtener precios por tamaño del catálogo
            $pricing = app(\App\Domain\Servicios\PricingService::class);
            $subServicio = 0.0;
            $totalCantidad = 0;
            $totalValorPonderado = 0;

            foreach ($cantidades as $tam => $qty) {
                if ($qty <= 0) continue;
                
                // Cada tamaño usa su propio precio del catálogo
                $pu = (float)$pricing->precioUnitario($orden->id_centrotrabajo, $servicio->servicio_id, $tam);
                $lineSub = $pu * (int)$qty;

                \App\Models\OTServicioItem::create([
                    'ot_servicio_id'    => $servicio->id,
                    'descripcion_item'  => ucfirst($tam),
                    'tamano'            => $tam,
                    'planeado'          => (int)$qty,
                    'completado'        => 0,
                    'precio_unitario'   => $pu,
                    'subtotal'          => $lineSub,
                ]);
                
                $subServicio += $lineSub;
                $totalCantidad += (int)$qty;
                $totalValorPonderado += $lineSub;
            }

            // Actualizar subtotal y precio_unitario promedio ponderado del servicio
            $servicio->subtotal = $subServicio;
            $servicio->precio_unitario = $totalCantidad > 0 ? ($totalValorPonderado / $totalCantidad) : 0;
            $servicio->save();

            // Recalcular totales de la OT
            $subtotalTotal = $orden->otServicios()->sum('subtotal');
            $ivaTotal = $subtotalTotal * 0.16;
            $totalTotal = $subtotalTotal + $ivaTotal;

            $orden->subtotal = $subtotalTotal;
            $orden->iva = $ivaTotal;
            $orden->total = $totalTotal;
            $orden->total_real = $subtotalTotal;
            $orden->save();

            // Registrar actividad
            $this->act('ordenes')
                ->performedOn($orden)
                ->event('definir_tamanos_servicio')
                ->withProperties(['servicio_id' => $servicio->id, 'cantidades' => $cantidades])
                ->log("OT #{$orden->id}: tamaños definidos para servicio #{$servicio->id}");
        });

        return back()->with('ok', 'Desglose por tamaños aplicado correctamente al servicio');
    }

    /** Asignar Team Leader */
    public function asignarTL(Request $req, Orden $orden)
    {
        $this->authorize('createFromSolicitud', [Orden::class, $orden->id_centrotrabajo]);
        $this->authorizeFromCentro($orden->id_centrotrabajo, $orden);

        $data = $req->validate([
            'team_leader_id' => ['required','integer','exists:users,id'],
        ]);

        $orden->team_leader_id = $data['team_leader_id'];
        if ($orden->estatus === 'generada') $orden->estatus = 'asignada';
        $orden->save();
        
        // Recargar la relación teamLeader para que esté disponible
        $orden->load('teamLeader');
        
        $this->act('ordenes')
            ->performedOn($orden)
            ->event('asignar_tl')
            ->withProperties(['team_leader_id' => $req->team_leader_id])
            ->log("OT #{$orden->id}: asignado TL {$req->team_leader_id}");

        // Notificar al TL asignado
        if ($orden->teamLeader) {
            $orden->teamLeader->notify(new OtAsignada($orden));
        }

        return back()->with('ok','Team Leader asignado');
    }

    /** Listado con filtros */
    public function index(Request $req)
    {
    $u = $req->user();
    // Privilegio de vista amplia: admin, facturación y gerente_upper (solo lectura)
    $isPrivilegedViewer = $u && method_exists($u, 'hasAnyRole') ? $u->hasAnyRole(['admin','facturacion','gerente_upper']) : false;
    $isTL = $u && method_exists($u, 'hasRole') ? $u->hasRole('team_leader') : false;
    // Si además de TL tiene otros roles con mayor alcance, no restringir el listado a sus OTs
    $isTLStrict = $u && method_exists($u, 'hasAnyRole')
        ? ($isTL && !$u->hasAnyRole(['admin','coordinador','calidad','facturacion','gerente_upper','Cliente_Supervisor','Cliente_Gerente']))
        : $isTL;
    $isCliente = $u && method_exists($u, 'hasRole') ? $u->hasRole('Cliente_Supervisor') : false;
    $isClienteCentro = $u && method_exists($u, 'hasRole') ? $u->hasRole('Cliente_Gerente') : false;

    $filters = [
            'estatus'      => $req->string('estatus')->toString(),
            'calidad'      => $req->string('calidad')->toString(),
            'servicio'     => $req->integer('servicio') ?: null,
            'centro'       => $req->integer('centro') ?: null,
            'centro_costo' => $req->integer('centro_costo') ?: null,
            'facturacion'  => $req->string('facturacion')->toString(),
            'desde'        => $req->date('desde'),
            'hasta'        => $req->date('hasta'),
            'id'           => $req->integer('id') ?: null,
            'year'         => $req->integer('year') ?: null,
            'week'         => $req->integer('week') ?: null,
        ];

    // Centros permitidos para el usuario
    $centrosPermitidos = $this->allowedCentroIds($u);

    // Si se solicitó filtrar por centro de costo y el usuario no es admin/facturacion,
    // validar que el centro de costo pertenezca a un centro permitido; si no, ignorar el filtro
    if (!$isPrivilegedViewer && !empty($filters['centro_costo'])) {
        $cc = \App\Models\CentroCosto::find($filters['centro_costo']);
        if (!$cc || !in_array((int)$cc->id_centrotrabajo, array_map('intval', $centrosPermitidos), true)) {
            $filters['centro_costo'] = null;
        }
    }
    $q = Orden::with(['servicio','centro','teamLeader','solicitud.centroCosto','solicitud.marca','factura','facturas','area'])
        ->when(!$isPrivilegedViewer, function($qq) use ($centrosPermitidos){
            if (!empty($centrosPermitidos)) { $qq->whereIn('id_centrotrabajo', $centrosPermitidos); }
            else { $qq->whereRaw('1=0'); }
        })
        ->when($isPrivilegedViewer && $filters['centro'], fn($qq)=>$qq->where('id_centrotrabajo', $filters['centro']))
        ->when(!$isPrivilegedViewer && $filters['centro'], function($qq) use ($filters, $centrosPermitidos){
            // Aplicar filtro solo si el centro está permitido
            if (in_array((int)$filters['centro'], array_map('intval',$centrosPermitidos), true)) {
                $qq->where('id_centrotrabajo', $filters['centro']);
            }
        })
        ->when($isTLStrict, fn($qq)=>$qq->where('team_leader_id',$u->id))
        ->when($isCliente && !$isClienteCentro, fn($qq)=>$qq->whereHas('solicitud', fn($w)=>$w->where('id_cliente',$u->id)))
            ->when($filters['id'], fn($qq,$v)=>$qq->where('id',$v))
            ->when($filters['estatus'], fn($qq,$v)=>$qq->where('estatus',$v))
            ->when($filters['calidad'], fn($qq,$v)=>$qq->where('calidad_resultado',$v))
            ->when($filters['servicio'], fn($qq,$v)=>$qq->where('id_servicio',$v))
            ->when($filters['centro_costo'], function($qq,$v){
                $qq->whereHas('solicitud', function($q) use ($v) { $q->where('id_centrocosto', $v); });
            })
            // Filtro por estatus de facturación
            ->when($filters['facturacion'] === 'sin_factura', function($qq){
                $qq->whereDoesntHave('factura')
                   ->whereDoesntHave('facturas');
            })
            ->when(in_array($filters['facturacion'], ['facturado','por_pagar','pagado'], true), function($qq) use ($filters){
                $qq->where(function($q) use ($filters){
                    $q->whereHas('facturas', function($w) use ($filters){ $w->where('estatus', $filters['facturacion']); })
                      ->orWhereHas('factura', function($w) use ($filters){ $w->where('estatus', $filters['facturacion']); });
                });
            })
            ->when($filters['desde'] && $filters['hasta'], fn($qq)=>$qq->whereBetween('created_at', [
                request()->date('desde')->startOfDay(), request()->date('hasta')->endOfDay(),
            ]))
            ->when($filters['year'] && $filters['week'], function($qq) use ($filters) {
                $qq->whereRaw('YEAR(created_at) = ? AND WEEK(created_at, 1) = ?', [$filters['year'], $filters['week']]);
            })
            ->when($filters['year'] && !$filters['week'], function($qq) use ($filters) {
                $qq->whereYear('created_at', $filters['year']);
            })
            ->orderByDesc('id');

    $isPeriod = !empty($filters['week']);

        // Reutilizamos el mismo mapeo para paginado o listado completo
        $transform = function ($o) {
            // Estatus de facturación real priorizando la factura en pivot (única por integridad)
            // Orden de prioridad: pivot -> directa -> fallback por estatus de OT
            $factStatus = 'sin_factura';
            if ($o->relationLoaded('facturas') && $o->facturas && $o->facturas->count() > 0) {
                $factStatus = $o->facturas->first()->estatus ?? 'facturado';
            } elseif ($o->relationLoaded('factura') && $o->factura) {
                $factStatus = $o->factura->estatus ?? 'facturado';
            } elseif ($o->estatus === 'facturada') {
                // Caso legado: la OT está marcada como 'facturada' pero no se cargó la factura
                $factStatus = 'facturado';
            }
            // Fecha exacta según BD (sin convertir TZ): formateada una sola vez
            $raw = $o->getRawOriginal('created_at');
            $fecha = null; $fechaIso = null;
            if ($raw) {
                try {
                    $dt = \Carbon\Carbon::parse($raw);
                    $fecha = $dt->format('Y-m-d H:i');
                    $fechaIso = $dt->toIso8601String();
                } catch (\Throwable $e) {
                    $fecha = substr($raw, 0, 16);
                }
            }
            return [
                'id' => $o->id,
                'estatus' => $o->estatus,
                'calidad_resultado' => $o->calidad_resultado,
                'facturacion' => $factStatus,
                'fecha' => $fecha,
                'producto' => $o->descripcion_general ?: ($o->solicitud?->descripcion ?? null),
                'servicio' => ['nombre' => $o->servicio?->nombre],
                'centro'   => ['nombre' => $o->centro?->nombre],
                'area'     => ['nombre' => $o->area?->nombre],
                'centro_costo' => ['nombre' => optional($o->solicitud?->centroCosto)->nombre],
                'marca'        => ['nombre' => optional($o->solicitud?->marca)->nombre],
                'team_leader' => ['name' => $o->teamLeader?->name],
                'urls' => [
                    'show'     => route('ordenes.show', $o),
                    'calidad'  => route('calidad.show',  $o),
                    'facturar' => route('facturas.createFromOrden', $o),
                ],
                'created_at_raw' => $raw,
                'fecha_iso' => $fechaIso,
            
                ];
        };

        if ($isPeriod) {
            // Mostrar todas las OTs del periodo (sin paginar)
            $all = $q->get()->map($transform)->values();
            // Enviamos en el mismo shape { data: [...] } para que el front lo consuma igual
            $data = [ 'data' => $all ];
        } else {
            $data = $q->paginate(10)->withQueryString();
            $data->getCollection()->transform($transform);
        }

        // Lista de centros para selector
        $centrosLista = $u->hasAnyRole(['admin','facturacion','gerente_upper'])
            ? \App\Models\CentroTrabajo::select('id','nombre')->orderBy('nombre')->get()
            : \App\Models\CentroTrabajo::whereIn('id', $centrosPermitidos)->select('id','nombre')->orderBy('nombre')->get();

        return Inertia::render('Ordenes/Index', [
            'data'      => $data,
            'filters'   => $req->only(['id','estatus','calidad','servicio','centro','centro_costo','facturacion','desde','hasta','year','week']),
            'servicios' => \App\Models\ServicioEmpresa::select('id','nombre')->orderBy('nombre')->get(),
            'centros'   => $centrosLista,
            'centrosCostos' => $u->hasAnyRole(['admin','facturacion','gerente_upper'])
                ? \App\Models\CentroCosto::select('id','nombre','id_centrotrabajo')->orderBy('nombre')->get()
                : \App\Models\CentroCosto::whereIn('id_centrotrabajo', $centrosPermitidos)->select('id','nombre','id_centrotrabajo')->orderBy('nombre')->get(),
            'urls'      => [
                'index' => route('ordenes.index'),
                'export' => route('ordenes.export'),
                'export_facturacion' => $u->hasAnyRole(['admin', 'facturacion', 'gerente_upper'])
                    ? route('ordenes.exportFacturacion')
                    : null,
                'facturas_batch' => route('facturas.batch'),
                'facturas_batch_create' => route('facturas.batch.create'),
            ],
        ]);
    }

    /** Exportar Excel con los mismos filtros del listado */
    public function export(Request $req)
    {
        $filters = $req->only([
            'id',
            'estatus',
            'calidad',
            'servicio',
            'centro',
            'centro_costo',
            'facturacion',
            'desde',
            'hasta',
            'year',
            'week',
        ]);

        $format = $req->get('format', 'xlsx');
        $file = 'ordenes_trabajo_' . now()->format('Ymd_His') . '.' . $format;

        return Excel::download(new OrdenesIndexExport($filters, $req->user()), $file);
    }

    /** Exportar Excel de facturación con el mismo filtro del listado (formato por item) */
    public function exportFacturacion(Request $req)
    {
        $filters = $req->only([
            'id',
            'estatus',
            'calidad',
            'servicio',
            'centro',
            'centro_costo',
            'facturacion',
            'desde',
            'hasta',
            'year',
            'week',
        ]);

        $format = $req->get('format', 'xlsx');
        $file = 'excel_facturacion_' . now()->format('Ymd_His') . '.' . $format;

        return Excel::download(new OrdenesFacturacionExport($filters, $req->user()), $file);
    }

    /** Helpers */
    private function authorizeFromCentro(int $centroId, ?Orden $orden=null): void
    {
        $u = Auth::user();
        if (!($u instanceof \App\Models\User)) abort(403);
        if ($u->hasAnyRole(['admin','facturacion'])) return; // acceso amplio

        $isTLStrict = $u->hasRole('team_leader') && !$u->hasAnyRole([
            'admin', 'coordinador', 'calidad', 'facturacion', 'gerente_upper', 'Cliente_Supervisor', 'Cliente_Gerente',
        ]);

        // Supervisor (antes 'cliente'): permitir si es dueño de la solicitud de la OT
        if ($orden && $u->hasRole('Cliente_Supervisor')) {
            if ($orden->solicitud && (int)$orden->solicitud->id_cliente === (int)$u->id) return;
        }

        // Calidad o Coordinador: permitir si el centro está en sus centros asignados (pivot) o su principal
        if ($u->hasAnyRole(['calidad','coordinador'])) {
            $ids = $this->allowedCentroIds($u);
            if (in_array((int)$centroId, array_map('intval', $ids), true)) {
                // Si es TL además, validar que la OT sea suya
                if ($orden && $isTLStrict && (int)$orden->team_leader_id !== (int)$u->id) {
                    Log::warning('authorizeFromCentro DENY (TL no coincide)', [
                        'user_id' => $u->id,
                        'roles' => $u->roles->pluck('name')->all(),
                        'orden_id' => optional($orden)->id,
                        'orden_tl' => optional($orden)->team_leader_id,
                    ]);
                    abort(403);
                }
                return;
            }
            Log::warning('authorizeFromCentro DENY (centro no permitido para calidad/coordinador)', [
                'user_id' => $u->id,
                'roles' => $u->roles->pluck('name')->all(),
                'centro_solicitado' => (int)$centroId,
                'centros_usuario' => $ids,
            ]);
            abort(403);
        }

        // Team Leader u otros: requerir mismo centro principal
        if ((int)$u->centro_trabajo_id !== (int)$centroId) {
            Log::warning('authorizeFromCentro DENY (centro principal no coincide)', [
                'user_id' => $u->id,
                'roles' => $u->roles->pluck('name')->all(),
                'user_centro' => (int)($u->centro_trabajo_id ?? 0),
                'centro_solicitado' => (int)$centroId,
            ]);
            abort(403);
        }
        if ($orden && $isTLStrict && (int)$orden->team_leader_id !== (int)$u->id) {
            Log::warning('authorizeFromCentro DENY (TL distinto a la OT)', [
                'user_id' => $u->id,
                'orden_id' => $orden->id,
                'orden_tl' => (int)$orden->team_leader_id,
            ]);
            abort(403);
        }
    }

    private function allowedCentroIds(\App\Models\User $u): array
    {
        if ($u->hasRole('admin')) return [];
        $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v)=>(int)$v)->all();
        $primary = (int)($u->centro_trabajo_id ?? 0);
        if ($primary) $ids[] = $primary;
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Agregar servicio adicional a una OT existente
     * Solo permitido para Coordinador y Team Leader
     */
    public function agregarServicioAdicional(Request $request, Orden $orden)
    {
        // Verificar permisos
        $user = $request->user();
        if (!$user->hasAnyRole(['admin', 'coordinador', 'team_leader'])) {
            abort(403, 'No tienes permiso para agregar servicios adicionales');
        }

        $this->authorize('view', $orden);
        $this->authorizeFromCentro($orden->id_centrotrabajo, $orden);

        // Validar datos
        $validated = $request->validate([
            'servicio_id' => 'required|exists:servicios_empresa,id',
            'nota' => 'required|string|max:1000',
        ]);

        // Validar que el servicio no esté ya asignado a esta OT
        $servicioYaAsignado = \App\Models\OTServicio::where('ot_id', $orden->id)
            ->where('servicio_id', $validated['servicio_id'])
            ->exists();

        if ($servicioYaAsignado) {
            return back()->withErrors([
                'servicio_id' => 'Este servicio ya está asignado a esta OT. Por favor selecciona un servicio diferente.'
            ])->withInput();
        }

        // Usar la cantidad planeada de la OT original
        $cantidadPlaneada = $orden->total_planeado ?: 1;

        DB::beginTransaction();
        try {
            // Si la OT es tradicional (no tiene servicios en pivot), migrar el servicio original
            if ($orden->otServicios()->count() === 0 && $orden->id_servicio) {
                \Log::info('🔄 Migrando OT tradicional a multiservicio', [
                    'ot_id' => $orden->id,
                    'servicio_original' => $orden->id_servicio,
                ]);

                // Obtener precio del servicio original
                $pricing = app(\App\Domain\Servicios\PricingService::class);
                $precioUnitario = $pricing->precioUnitario(
                    $orden->id_centrotrabajo,
                    $orden->id_servicio,
                    null // sin tamaño específico para servicio tradicional
                );

                // Crear registro para el servicio original como "SOLICITADO"
                $servicioOriginal = \App\Models\OTServicio::create([
                    'ot_id' => $orden->id,
                    'servicio_id' => $orden->id_servicio,
                    'tipo_cobro' => 'pieza',
                    'cantidad' => $orden->total_planeado ?: 1,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $precioUnitario * ($orden->total_planeado ?: 1),
                    'origen' => 'SOLICITADO',
                ]);

                // Migrar items existentes al nuevo servicio
                if ($orden->items()->exists()) {
                    foreach ($orden->items as $item) {
                        \App\Models\OTServicioItem::create([
                            'ot_servicio_id' => $servicioOriginal->id,
                            'descripcion_item' => $item->descripcion ?: 'Item migrado',
                            'tamano' => $item->tamano,
                            'planeado' => $item->cantidad_planeada,
                            'completado' => $item->cantidad_real,
                            'faltante' => $item->faltante ?: 0,
                            'precio_unitario' => $item->precio_unitario,
                            'subtotal' => $item->subtotal,
                        ]);
                    }
                }

                \Log::info('✅ Servicio original migrado a ot_servicios', [
                    'ot_servicio_id' => $servicioOriginal->id,
                ]);
            }

            // Obtener precio del nuevo servicio
            $pricing = app(\App\Domain\Servicios\PricingService::class);
            $precioUnitario = $pricing->precioUnitario(
                $orden->id_centrotrabajo,
                $validated['servicio_id'],
                null
            );

            // Crear el servicio adicional
            $servicioAdicional = \App\Models\OTServicio::create([
                'ot_id' => $orden->id,
                'servicio_id' => $validated['servicio_id'],
                'tipo_cobro' => 'pieza',
                'cantidad' => $cantidadPlaneada,
                'precio_unitario' => $precioUnitario,
                'subtotal' => $precioUnitario * $cantidadPlaneada,
                'origen' => 'ADICIONAL',
                'added_by_user_id' => $user->id,
                'nota' => $validated['nota'],
            ]);

            // Crear item por defecto para el servicio adicional
            \App\Models\OTServicioItem::create([
                'ot_servicio_id' => $servicioAdicional->id,
                'descripcion_item' => 'Servicio adicional',
                'planeado' => $cantidadPlaneada,
                'completado' => 0,
                'faltante' => 0,
            ]);

            // Recalcular totales de la OT
            $orden->recalcTotals();

            // Log de actividad
            $this->act('ordenes')
                ->performedOn($orden)
                ->event('servicio_adicional')
                ->withProperties([
                    'servicio_id' => $validated['servicio_id'],
                    'unidades' => $cantidadPlaneada,
                    'nota' => $validated['nota'],
                ])
                ->log("OT #{$orden->id}: servicio adicional agregado por {$user->name}");

            DB::commit();

            return back()->with('success', 'Servicio adicional agregado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('❌ Error al agregar servicio adicional', [
                'ot_id' => $orden->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Error al agregar servicio adicional: ' . $e->getMessage()]);
        }
    }

    /**
     * Obtener servicios disponibles para un centro de trabajo
     */
    private function getServiciosDisponibles(int $centroId): array
    {
        return \App\Models\ServicioCentro::where('id_centrotrabajo', $centroId)
            ->with('servicio')
            ->get()
            ->map(function ($servicioCentro) {
                return [
                    'id' => $servicioCentro->id_servicio, // Corregido: era servicio_id
                    'nombre' => $servicioCentro->servicio->nombre,
                    'precio_base' => $servicioCentro->precio_base,
                ];
            })
            ->toArray();
    }

    private function buildFolioOT(int $centroId): string
    {
        $pref = 'UPP';
        $yyyymm = now()->format('Ym');
        $seq = Orden::where('id_centrotrabajo', $centroId)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count() + 1;

        return sprintf('%s-%s-%04d', $pref, $yyyymm, $seq);
    }

    private function act(string $log)
    {
        return app(\Spatie\Activitylog\ActivityLogger::class)->useLog($log);
    }
}
