<?php

// app/Http/Controllers/OrdenController.php
namespace App\Http\Controllers;


use App\Models\Solicitud;
use App\Models\Orden;
use App\Models\OrdenItem;
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
use App\Services\Notifier;
use App\Jobs\GenerateOrdenPdf;

class OrdenController extends Controller
{
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
        $orden->load(['servicio','centro','teamLeader','items']);
        $pdf = PDF::loadView('pdf.orden', ['orden'=>$orden])->setPaper('letter');
        return $pdf->stream("OT_{$orden->id}.pdf");
    }

    /** Form: Generar OT desde solicitud aprobada */
    public function createFromSolicitud(Solicitud $solicitud)
    {
        $this->authorize('createFromSolicitud', [Orden::class, $solicitud->id_centrotrabajo]);
        $this->authorizeFromCentro($solicitud->id_centrotrabajo);
        if ($solicitud->estatus !== 'aprobada') abort(422, 'La solicitud no está aprobada.');
        // Evitar generar más de una OT por solicitud
        if ($solicitud->ordenes()->exists()) {
            return redirect()->route('solicitudes.show', $solicitud->id)
                ->withErrors(['ot' => 'Ya existe una Orden de Trabajo para esta solicitud.']);
        }

    // Cargar relaciones necesarias
    $solicitud->load(['servicio','centro','tamanos','centroCosto','marca']);

        // Determinar si el servicio usa tamaños
        $usaTamanos = (bool)($solicitud->servicio->usa_tamanos ?? false);
        $prefill = [];

        if ($usaTamanos && $solicitud->tamanos->count() > 0) {
            // Servicios CON tamaños: items fijos basados en tamaños de solicitud
            foreach ($solicitud->tamanos as $t) {
                $prefill[] = [
                    'tamano'      => (string)($t->tamano ?? ''),
                    'cantidad'    => (int)($t->cantidad ?? 0),
                    'descripcion' => null,
                    'editable'    => false, // NO permitir editar
                ];
            }
        } else {
            // Caso 1: Servicios CON tamaños PERO sin desglose aún (flujo diferido)
            // Caso 2: Servicios SIN tamaños
            $prefill[] = [
                'descripcion' => $solicitud->descripcion ?? 'Item',
                'cantidad'    => (int)($solicitud->cantidad ?? 1),
                'tamano'      => null,
                'editable'    => true, // Permitir separar
            ];
        }

        $teamLeaders = User::role('team_leader')
            ->where('centro_trabajo_id', $solicitud->id_centrotrabajo)
            ->select('id','name')->orderBy('name')->get();

        // Calcular cotización (mismos criterios que en Show de Solicitudes)
        $pricing = app(\App\Domain\Servicios\PricingService::class);
        $ivaRate = 0.16;
        $cotLines = [];
        $sub = 0.0;
        
        if ($usaTamanos && $solicitud->tamanos && $solicitud->tamanos->count() > 0) {
            foreach ($solicitud->tamanos as $t) {
                $tam = (string)($t->tamano ?? '');
                $cant = (int)($t->cantidad ?? 0);
                if ($cant <= 0) continue;
                $pu = (float)$pricing->precioUnitario($solicitud->id_centrotrabajo, $solicitud->id_servicio, $tam);
                $lineSub = $pu * $cant;
                $sub += $lineSub;
                $cotLines[] = ['label'=>ucfirst($tam), 'cantidad'=>$cant, 'pu'=>$pu, 'subtotal'=>$lineSub];
            }
        } elseif ($usaTamanos) {
            // Flujo diferido: sin desglose aún, mostrar totales en cero explícitamente
            $cotLines[] = ['label'=>'Item', 'cantidad'=>(int)($solicitud->cantidad ?? 0), 'pu'=>0.0, 'subtotal'=>0.0];
            $sub = 0.0;
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
                'servicio' => [ 'nombre' => $solicitud->servicio->nombre ?? null ],
                'centro'   => [ 'nombre' => $solicitud->centro->nombre ?? null ],
                'centroCosto' => $solicitud->centroCosto ? $solicitud->centroCosto->only(['id','nombre']) : null,
                'marca'       => $solicitud->marca ? $solicitud->marca->only(['id','nombre']) : null,
            ],
            'folio'               => $this->buildFolioOT($solicitud->id_centrotrabajo),
            'teamLeaders'         => $teamLeaders,
            'prefill'             => $prefill,
            'usaTamanos'          => $usaTamanos,
            'cantidadTotal'       => (int)($solicitud->cantidad ?? 1),
            'descripcionGeneral'  => $solicitud->descripcion ?? '', // Nombre del producto general
            'cotizacion'          => $cot,
            'urls'                => [
                'store' => route('ordenes.storeFromSolicitud', $solicitud),
            ],
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

        $solicitud->load('servicio', 'tamanos');
        $usaTamanos = (bool)($solicitud->servicio->usa_tamanos ?? false);

        // Validación base
        $data = $req->validate([
            'team_leader_id' => ['nullable','integer','exists:users,id'],
            'id_area' => ['nullable','integer','exists:areas,id'],
            'separar_items'  => ['nullable','boolean'],
            'items'          => ['required','array','min:1'],
            'items.*.cantidad' => ['required','integer','min:1'],
            'items.*.descripcion' => ['nullable','string','max:255'], // IMPORTANTE: Incluir aquí para que Laravel no lo elimine
            'items.*.tamano' => ['nullable','string'],
        ]);

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

        $orden = DB::transaction(function () use ($solicitud, $data, $usaTamanos, $separarItems) {
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
                $teamLeader->notify(new OtAsignada($orden));
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

        $data = $req->validate([
            'items'                 => ['required','array','min:1'],
            'items.*.id_item'       => ['required','integer','exists:orden_items,id'],
            'items.*.cantidad'      => ['required','integer','min:1'],
            'comentario'            => ['nullable','string','max:500'],
        ]);

        $justCompleted = false;
        DB::transaction(function () use ($orden, $data, $req, &$justCompleted) {
            $pricing = app(\App\Domain\Servicios\PricingService::class);
            foreach ($data['items'] as $i) {
                $item = \App\Models\OrdenItem::where('id', $i['id_item'])
                    ->where('id_orden', $orden->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $nuevo = (int)$item->cantidad_real + (int)$i['cantidad'];
                if ($nuevo > (int)$item->cantidad_planeada) {
                    $nuevo = (int)$item->cantidad_planeada;
                }

                // Determinar un PU efectivo robusto
                $puEfectivo = (float)$item->precio_unitario;
                if ($puEfectivo <= 0) {
                    // Intentar resolver desde PricingService (según centro, servicio y tamaño)
                    $puEfectivo = (float)$pricing->precioUnitario($orden->id_centrotrabajo, $orden->id_servicio, $item->tamano);
                }
                if ($puEfectivo <= 0) {
                    // Último recurso: derivar del subtotal actual y mejor base de cantidad
                    $baseQty = ((int)$item->cantidad_real > 0) ? (int)$item->cantidad_real : max(1, (int)$item->cantidad_planeada);
                    $puEfectivo = (float)$item->subtotal / max(1, $baseQty);
                }

                $item->precio_unitario = $item->precio_unitario > 0 ? $item->precio_unitario : $puEfectivo;
                $item->cantidad_real = $nuevo;
                $item->subtotal = $puEfectivo * $nuevo;
                $item->save();
            }

            // totales y estatus
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
                $orden->fecha_completada = now();
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
                    Notification::send($usuariosCalidad, new OtListaParaCalidad($orden));
                }
            } else {
                $orden->save();
            }

            // Registrar avances en la tabla de avances
            // Marcar como corregido si la orden fue rechazada previamente por calidad
            $isCorregido = \App\Models\Aprobacion::where('aprobable_type', \App\Models\Orden::class)
                ->where('aprobable_id', $orden->id)
                ->where('tipo', 'calidad')
                ->where('resultado', 'rechazado')
                ->exists();
            
            foreach ($data['items'] as $d) {
                if ((int)$d['cantidad'] > 0) {
                    \App\Models\Avance::create([
                        'id_orden' => $orden->id,
                        'id_item' => $d['id_item'],
                        'id_usuario' => Auth::id(),
                        'cantidad' => (int)$d['cantidad'],
                        'comentario' => $req->comentario ?? null,
                        'es_corregido' => $isCorregido,
                    ]);
                }
            }

            // Registrar en activity log
            $this->act('ordenes')
                ->performedOn($orden)
                ->event('avance')
                ->withProperties(['items' => $data['items'], 'comentario' => $req->comentario])
                ->log("OT #{$orden->id}: avance registrado");
        });

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
                    // Calcular PU confiable: usar precio_unitario si existe, de lo contrario derivarlo del subtotal actual
                    $baseQty = ((int)$item->cantidad_real > 0) ? (int)$item->cantidad_real : max(1, (int)$item->cantidad_planeada);
                    $puEfectivo = (float)$item->precio_unitario;
                    if ($puEfectivo <= 0) {
                        $puEfectivo = (float)$item->subtotal / max(1, $baseQty);
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
                    if ($item->precio_unitario <= 0 && $puEfectivo > 0) {
                        $item->precio_unitario = $puEfectivo;
                    }
                    $item->subtotal = $puEfectivo * (int)$item->cantidad_real;
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
                    $orden->fecha_completada = now();
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
            'avances' => fn($q) => $q->with(['usuario', 'item'])->orderByDesc('created_at'),
            'evidencias' => fn($q)=>$q->with('usuario')->orderByDesc('id'),
        ]);

        // Flag para flujo diferido: usa tamaños y aún no hay desglose en solicitud
        $pendienteTamanos = (bool)($orden->servicio?->usa_tamanos) && ($orden->solicitud && $orden->solicitud->tamanos->count() === 0);

        $canReportar = Gate::allows('reportarAvance', $orden);
        $authUser = Auth::user();
        $isAdminOrCoord = false;
        if ($authUser instanceof \App\Models\User) {
            $isAdminOrCoord = $authUser->hasAnyRole(['admin','coordinador']);
        }
        $canAsignar  = $isAdminOrCoord && $orden->estatus !== 'completada';

        // Permisos específicos adicionales
        $canCalidad = false; $canClienteAutorizar = false; $canFacturar = false;
        if ($authUser instanceof \App\Models\User) {
            // Calidad: admin o rol calidad con centro permitido (pivot + principal), OT completada y pendiente
            if ($orden->estatus === 'completada' && $orden->calidad_resultado === 'pendiente') {
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
            $canClienteAutorizar = ($authUser->hasRole('admin') || ($orden->solicitud && (int)$orden->solicitud->id_cliente === (int)$authUser->id))
                && $orden->calidad_resultado === 'validado'
                && $orden->estatus === 'completada';

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
        foreach ($orden->items as $it) {
            $qty = ($it->cantidad_real ?? 0) > 0 ? (int)$it->cantidad_real : (int)$it->cantidad_planeada;
            $lineSub = (float)$it->precio_unitario * $qty;
            $sub += $lineSub;
            $lines[] = [
                'label'    => $it->tamano ? ('Tamaño: '.ucfirst($it->tamano)) : ($it->descripcion ?: 'Item'),
                'cantidad' => $qty,
                'pu'       => (float)$it->precio_unitario,
                'subtotal' => $lineSub,
            ];
        }
        $cot = ['lines'=>$lines, 'subtotal'=>$sub, 'iva_rate'=>$ivaRate, 'iva'=>$sub*$ivaRate, 'total'=>$sub*(1+$ivaRate)];

        // Precios unitarios por tamaño para vista de desglose (flujo diferido)
        $preciosTamaño = null;
        if ($orden->servicio && (bool)$orden->servicio->usa_tamanos) {
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

        return Inertia::render('Ordenes/Show', [
            'orden'       => $orden,
            'can'         => [
                'reportarAvance'     => $canReportar,
                'asignar_tl'         => $canAsignar,
                'calidad_validar'    => $canCalidad,
                'cliente_autorizar'  => $canClienteAutorizar,
                'facturar'           => $canFacturar,
            ],
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
                'calidad_page'      => route('calidad.show', $orden),
                'calidad_validar'   => route('calidad.validar', $orden),
                'calidad_rechazar'  => route('calidad.rechazar', $orden),
                'cliente_autorizar' => route('cliente.autorizar', $orden),
                'facturar'          => route('facturas.createFromOrden', $orden),
                'pdf'               => route('ordenes.pdf', $orden),
                'evidencias_store'  => route('evidencias.store', $orden),
                'evidencias_destroy'=> route('evidencias.destroy', 0),
                'definir_tamanos'   => route('ordenes.definirTamanos', $orden),
            ],
            'flags' => [ 'pendiente_tamanos' => $pendienteTamanos ],
            'precios_tamano' => $preciosTamaño,
        ]);
    }

    /** Definir desglose por tamaños para OT (flujo diferido) */
    public function definirTamanos(Request $req, Orden $orden)
    {
        $this->authorize('createFromSolicitud', [Orden::class, $orden->id_centrotrabajo]);
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
    $isAdminOrFact = $u && method_exists($u, 'hasAnyRole') ? $u->hasAnyRole(['admin','facturacion']) : false;
    $isTL = $u && method_exists($u, 'hasRole') ? $u->hasRole('team_leader') : false;
    $isCliente = $u && method_exists($u, 'hasRole') ? $u->hasRole('cliente') : false;
    $isClienteCentro = $u && method_exists($u, 'hasRole') ? $u->hasRole('cliente_centro') : false;

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
    if (!$isAdminOrFact && !empty($filters['centro_costo'])) {
        $cc = \App\Models\CentroCosto::find($filters['centro_costo']);
        if (!$cc || !in_array((int)$cc->id_centrotrabajo, array_map('intval', $centrosPermitidos), true)) {
            $filters['centro_costo'] = null;
        }
    }
    $q = Orden::with(['servicio','centro','teamLeader','solicitud.centroCosto','solicitud.marca','factura','facturas','area'])
        ->when(!$isAdminOrFact, function($qq) use ($centrosPermitidos){
            if (!empty($centrosPermitidos)) { $qq->whereIn('id_centrotrabajo', $centrosPermitidos); }
            else { $qq->whereRaw('1=0'); }
        })
        ->when($isAdminOrFact && $filters['centro'], fn($qq)=>$qq->where('id_centrotrabajo', $filters['centro']))
        ->when(!$isAdminOrFact && $filters['centro'], function($qq) use ($filters, $centrosPermitidos){
            // Aplicar filtro solo si el centro está permitido
            if (in_array((int)$filters['centro'], array_map('intval',$centrosPermitidos), true)) {
                $qq->where('id_centrotrabajo', $filters['centro']);
            }
        })
        ->when($isTL, fn($qq)=>$qq->where('team_leader_id',$u->id))
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
        $centrosLista = $u->hasAnyRole(['admin','facturacion'])
            ? \App\Models\CentroTrabajo::select('id','nombre')->orderBy('nombre')->get()
            : \App\Models\CentroTrabajo::whereIn('id', $centrosPermitidos)->select('id','nombre')->orderBy('nombre')->get();

        return Inertia::render('Ordenes/Index', [
            'data'      => $data,
            'filters'   => $req->only(['id','estatus','calidad','servicio','centro','centro_costo','facturacion','desde','hasta','year','week']),
            'servicios' => \App\Models\ServicioEmpresa::select('id','nombre')->orderBy('nombre')->get(),
            'centros'   => $centrosLista,
            'centrosCostos' => $u->hasAnyRole(['admin','facturacion'])
                ? \App\Models\CentroCosto::select('id','nombre','id_centrotrabajo')->orderBy('nombre')->get()
                : \App\Models\CentroCosto::whereIn('id_centrotrabajo', $centrosPermitidos)->select('id','nombre','id_centrotrabajo')->orderBy('nombre')->get(),
            'urls'      => [
                'index' => route('ordenes.index'),
                'facturas_batch' => route('facturas.batch'),
                'facturas_batch_create' => route('facturas.batch.create'),
            ],
        ]);
    }

    /** Helpers */
    private function authorizeFromCentro(int $centroId, ?Orden $orden=null): void
    {
        $u = Auth::user();
        if (!($u instanceof \App\Models\User)) abort(403);
        if ($u->hasAnyRole(['admin','facturacion'])) return; // acceso amplio

        // Cliente: permitir si es dueño de la solicitud de la OT
        if ($orden && $u->hasRole('cliente')) {
            if ($orden->solicitud && (int)$orden->solicitud->id_cliente === (int)$u->id) return;
        }

        // Calidad o Coordinador: permitir si el centro está en sus centros asignados (pivot) o su principal
        if ($u->hasAnyRole(['calidad','coordinador'])) {
            $ids = $this->allowedCentroIds($u);
            if (in_array((int)$centroId, array_map('intval', $ids), true)) {
                // Si es TL además, validar que la OT sea suya
                if ($orden && $u->hasRole('team_leader') && (int)$orden->team_leader_id !== (int)$u->id) {
                    abort(403);
                }
                return;
            }
            abort(403);
        }

        // Team Leader u otros: requerir mismo centro principal
        if ((int)$u->centro_trabajo_id !== (int)$centroId) abort(403);
        if ($orden && $u->hasRole('team_leader') && (int)$orden->team_leader_id !== (int)$u->id) abort(403);
    }

    private function allowedCentroIds(\App\Models\User $u): array
    {
        if ($u->hasRole('admin')) return [];
        $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v)=>(int)$v)->all();
        $primary = (int)($u->centro_trabajo_id ?? 0);
        if ($primary) $ids[] = $primary;
        return array_values(array_unique(array_filter($ids)));
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
