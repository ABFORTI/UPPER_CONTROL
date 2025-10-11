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

        // Cargar relaciones necesarias y preparar prefill de items desde los tamaños (si existen)
    $solicitud->load(['servicio','centro','tamanos']);

        $prefill = [];
        if ($solicitud->relationLoaded('tamanos') && $solicitud->tamanos->count() > 0) {
            foreach ($solicitud->tamanos as $t) {
                $tam = (string)($t->tamano ?? '');
                $desc = trim(($solicitud->descripcion ?? '') . ($tam ? " (".ucfirst($tam).")" : ''));
                $prefill[] = [
                    'descripcion' => $desc ?: 'Item',
                    'cantidad'    => (int)($t->cantidad ?? 0),
                    'tamano'      => $tam ?: null,
                ];
            }
        } else {
            // Fallback: un solo renglón con la descripción/cantidad de la solicitud
            $prefill[] = [
                'descripcion' => $solicitud->descripcion ?? 'Item',
                'cantidad'    => (int)($solicitud->cantidad ?? 1),
                'tamano'      => null,
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
        if ($solicitud->tamanos && $solicitud->tamanos->count() > 0) {
            foreach ($solicitud->tamanos as $t) {
                $tam = (string)($t->tamano ?? '');
                $cant = (int)($t->cantidad ?? 0);
                if ($cant <= 0) continue;
                $pu = (float)$pricing->precioUnitario($solicitud->id_centrotrabajo, $solicitud->id_servicio, $tam);
                $lineSub = $pu * $cant;
                $sub += $lineSub;
                $cotLines[] = ['label'=>ucfirst($tam), 'cantidad'=>$cant, 'pu'=>$pu, 'subtotal'=>$lineSub];
            }
        } else {
            $pu = (float)$pricing->precioUnitario($solicitud->id_centrotrabajo, $solicitud->id_servicio, null);
            $sub = $pu * (int)($solicitud->cantidad ?? 0);
            $cotLines[] = ['label'=>'Pieza', 'cantidad'=>(int)($solicitud->cantidad ?? 0), 'pu'=>$pu, 'subtotal'=>$sub];
        }
        $cot = ['lines'=>$cotLines, 'subtotal'=>$sub, 'iva_rate'=>$ivaRate, 'iva'=>$sub*$ivaRate, 'total'=>$sub*(1+$ivaRate)];

        return Inertia::render('Ordenes/CreateFromSolicitud', [
            'solicitud'   => $solicitud->only('id','descripcion','cantidad','id_servicio','id_centrotrabajo'),
            'folio'       => $this->buildFolioOT($solicitud->id_centrotrabajo),
            'teamLeaders' => $teamLeaders,
            'prefill'     => $prefill,
            'cotizacion'  => $cot,
            'urls'        => [
                'store' => route('ordenes.storeFromSolicitud', $solicitud),
            ],
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

        $data = $req->validate([
            'team_leader_id'       => ['nullable','integer','exists:users,id'],
            'items'                => ['required','array','min:1'],
            'items.*.descripcion'  => ['required','string','max:255'],
            'items.*.cantidad'     => ['required','integer','min:1'],
            'items.*.tamano'       => ['nullable','string','max:50'],
        ]);

    $orden = DB::transaction(function () use ($solicitud, $data) {
            $totalPlan = collect($data['items'])->sum(fn($i) => (int)($i['cantidad'] ?? 0));

            $orden = Orden::create([
                'folio'            => $this->buildFolioOT($solicitud->id_centrotrabajo),
                'id_solicitud'     => $solicitud->id,
                'id_centrotrabajo' => $solicitud->id_centrotrabajo,
                'id_servicio'      => $solicitud->id_servicio,
                'id_area'          => $solicitud->id_area,
                'team_leader_id'   => $data['team_leader_id'] ?? null,
                'estatus'          => !empty($data['team_leader_id']) ? 'asignada' : 'generada',
                'total_planeado'   => $totalPlan,
                'total_real'       => 0,
                'calidad_resultado'=> 'pendiente',
            ]);

            // Resolver precios unitarios por item (por tamaño si aplica)
            $pricing = app(\App\Domain\Servicios\PricingService::class);
            $sub = 0.0;
            foreach ($data['items'] as $it) {
                $tamano = $it['tamano'] ?? null;
                $pu = (float)$pricing->precioUnitario($solicitud->id_centrotrabajo, $solicitud->id_servicio, $tamano);

                OrdenItem::create([
                    'id_orden'          => $orden->id,
                    'descripcion'       => $it['descripcion'],
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

        // Notificar a TL si existe
        if ($orden->team_leader_id) {
            Notifier::toUser(
                $orden->team_leader_id,
                'OT asignada',
                "Se te asignó la OT #{$orden->id}.",
                route('ordenes.show',$orden->id)
            );
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
            foreach ($data['items'] as $i) {
                $item = \App\Models\OrdenItem::where('id', $i['id_item'])
                    ->where('id_orden', $orden->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $nuevo = (int)$item->cantidad_real + (int)$i['cantidad'];
                if ($nuevo > (int)$item->cantidad_planeada) {
                    $nuevo = (int)$item->cantidad_planeada;
                }

                $item->update([
                    'cantidad_real' => $nuevo,
                    'subtotal'      => (float)$item->precio_unitario * $nuevo,
                ]);
            }

            // totales y estatus
            $sumReal = $orden->items()->sum('cantidad_real');
            $sumPlan = $orden->items()->sum('cantidad_planeada');

            $orden->total_real = $orden->items()
                ->selectRaw('COALESCE(SUM(cantidad_real * precio_unitario),0) as t')->value('t');

            $justCompleted = ($orden->estatus !== 'completada') && ($sumReal >= $sumPlan && $sumPlan > 0);
            if ($justCompleted) {
                $orden->estatus = 'completada';
                $orden->save();

                // Notificar a calidad del centro
                Notifier::toRoleInCentro(
                    'calidad',
                    $orden->id_centrotrabajo,
                    'OT lista para calidad',
                    "La OT #{$orden->id} quedó completada y espera validación.",
                    route('calidad.show',$orden->id)
                );
            } else {
                $orden->save();
            }

            // (opcional) registrar bitácora de avance en una tabla aparte
            // \App\Models\Avance::create([...]);
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

    /** Detalle de OT */
    public function show(Orden $orden)
    {
        $this->authorize('view', $orden);
        $this->authorizeFromCentro($orden->id_centrotrabajo, $orden);

        $orden->load([
            'solicitud.archivos','servicio','centro','area','items','teamLeader','avances.usuario',
            // Deja la línea de abajo SOLO si existe la relación:
            // 'items.evidencias',
            'evidencias' => fn($q)=>$q->with('usuario')->orderByDesc('id'),
        ]);

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
            'urls'        => [
                'asignar_tl'        => route('ordenes.asignarTL', $orden),
                'avances_store'     => route('ordenes.avances.store', $orden),
                'calidad_page'      => route('calidad.show', $orden),
                'calidad_validar'   => route('calidad.validar', $orden),
                'calidad_rechazar'  => route('calidad.rechazar', $orden),
                'cliente_autorizar' => route('cliente.autorizar', $orden),
                'facturar'          => route('facturas.createFromOrden', $orden),
                'pdf'               => route('ordenes.pdf', $orden),
                'evidencias_store'  => route('evidencias.store', $orden),
                'evidencias_destroy'=> route('evidencias.destroy', 0),
            ],
        ]);
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
        $this->act('ordenes')
            ->performedOn($orden)
            ->event('asignar_tl')
            ->withProperties(['team_leader_id' => $req->team_leader_id])
            ->log("OT #{$orden->id}: asignado TL {$req->team_leader_id}");

        // Notificar al TL asignado
        optional($orden->teamLeader)->notify(new OtAsignada($orden));

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
            'estatus'  => $req->string('estatus')->toString(),
            'calidad'  => $req->string('calidad')->toString(),
            'servicio' => $req->integer('servicio') ?: null,
            'desde'    => $req->date('desde'),
            'hasta'    => $req->date('hasta'),
            'id'       => $req->integer('id') ?: null,
        ];

    $q = Orden::with(['servicio','centro','teamLeader','solicitud','factura','area'])
        ->when(!$isAdminOrFact, fn($qq)=>$qq->where('id_centrotrabajo',$u->centro_trabajo_id))
        ->when($isTL, fn($qq)=>$qq->where('team_leader_id',$u->id))
        ->when($isCliente && !$isClienteCentro, fn($qq)=>$qq->whereHas('solicitud', fn($w)=>$w->where('id_cliente',$u->id)))
            ->when($filters['id'], fn($qq,$v)=>$qq->where('id',$v))
            ->when($filters['estatus'], fn($qq,$v)=>$qq->where('estatus',$v))
            ->when($filters['calidad'], fn($qq,$v)=>$qq->where('calidad_resultado',$v))
            ->when($filters['servicio'], fn($qq,$v)=>$qq->where('id_servicio',$v))
            ->when($filters['desde'] && $filters['hasta'], fn($qq)=>$qq->whereBetween('created_at', [
                request()->date('desde')->startOfDay(), request()->date('hasta')->endOfDay(),
            ]))
            ->orderByDesc('id');

        $data = $q->paginate(10)->withQueryString();

        $data->getCollection()->transform(function ($o) {
            // Derivar estatus de facturación: si hay factura vinculada, usar su estatus; si no, "sin_factura".
            $factStatus = optional($o->factura)->estatus ?? 'sin_factura';
            return [
                'id' => $o->id,
                'estatus' => $o->estatus,
                'calidad_resultado' => $o->calidad_resultado,
                'facturacion' => $factStatus,
                'created_at' => $o->created_at,
                'servicio' => ['nombre' => $o->servicio?->nombre],
                'centro'   => ['nombre' => $o->centro?->nombre],
                'area'     => ['nombre' => $o->area?->nombre],
                'team_leader' => ['name' => $o->teamLeader?->name],
                'urls' => [
                    'show'     => route('ordenes.show', $o),
                    'calidad'  => route('calidad.show',  $o),
                    'facturar' => route('facturas.createFromOrden', $o),
                ],
            
                ]    ;
        });

        return Inertia::render('Ordenes/Index', [
            'data'      => $data,
            'filters'   => $req->only(['id','estatus','calidad','servicio','desde','hasta']),
            'servicios' => \App\Models\ServicioEmpresa::select('id','nombre')->orderBy('nombre')->get(),
            'urls'      => ['index' => route('ordenes.index')],
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
