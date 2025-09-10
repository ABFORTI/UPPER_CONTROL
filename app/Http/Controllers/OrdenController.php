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
            return Storage::download($orden->pdf_path, "OT_{$orden->id}.pdf");
        }

        // Fallback: generar al vuelo (por si el worker aún no corrió)
        $orden->load(['servicio','centro','teamLeader','items']);
        $pdf = PDF::loadView('pdf.orden', ['orden'=>$orden])->setPaper('letter');
        return $pdf->download("OT_{$orden->id}.pdf");
    }

    /** Form: Generar OT desde solicitud aprobada */
    public function createFromSolicitud(Solicitud $solicitud)
    {
        $this->authorize('createFromSolicitud', [Orden::class, $solicitud->id_centrotrabajo]);
        $this->authorizeFromCentro($solicitud->id_centrotrabajo);
        if ($solicitud->estatus !== 'aprobada') abort(422, 'La solicitud no está aprobada.');

        $teamLeaders = User::role('team_leader')
            ->where('centro_trabajo_id', $solicitud->id_centrotrabajo)
            ->select('id','name')->orderBy('name')->get();

        return Inertia::render('Ordenes/CreateFromSolicitud', [
            'solicitud'   => $solicitud->only('id','descripcion','cantidad','id_servicio','id_centrotrabajo'),
            'folio'       => $this->buildFolioOT($solicitud->id_centrotrabajo),
            'teamLeaders' => $teamLeaders,
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

        $data = $req->validate([
            'team_leader_id'       => ['nullable','integer','exists:users,id'],
            'items'                => ['required','array','min:1'],
            'items.*.descripcion'  => ['required','string','max:255'],
            'items.*.cantidad'     => ['required','integer','min:1'],
        ]);

        $orden = DB::transaction(function () use ($solicitud, $data) {
            $totalPlan = collect($data['items'])->sum(fn($i) => (int)($i['cantidad'] ?? 0));

            $orden = Orden::create([
                'folio'            => $this->buildFolioOT($solicitud->id_centrotrabajo),
                'id_solicitud'     => $solicitud->id,
                'id_centrotrabajo' => $solicitud->id_centrotrabajo,
                'id_servicio'      => $solicitud->id_servicio,
                'team_leader_id'   => $data['team_leader_id'] ?? null,
                'estatus'          => !empty($data['team_leader_id']) ? 'asignada' : 'generada',
                'total_planeado'   => $totalPlan,
                'total_real'       => 0,
                'calidad_resultado'=> 'pendiente',
            ]);

            foreach ($data['items'] as $it) {
                OrdenItem::create([
                    'id_orden'          => $orden->id,
                    'descripcion'       => $it['descripcion'],
                    'cantidad_planeada' => (int)$it['cantidad'],
                    'precio_unitario'   => 0,
                    'subtotal'          => 0,
                ]);
            }

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

        GenerateOrdenPdf::dispatch($orden->id);

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
            'solicitud','servicio','centro','items','teamLeader','avances.usuario',
            // Deja la línea de abajo SOLO si existe la relación:
            // 'items.evidencias',
            'evidencias' => fn($q)=>$q->with('usuario')->orderByDesc('id'),
        ]);

        $canReportar = Gate::allows('reportarAvance', $orden);
        $canAsignar  = auth()->user()->hasAnyRole(['admin','coordinador'])
                      && $orden->estatus !== 'completada';

        $teamLeaders = $canAsignar
            ? User::role('team_leader')
                ->where('centro_trabajo_id',$orden->id_centrotrabajo)
                ->select('id','name')->orderBy('name')->get()
            : [];

        return Inertia::render('Ordenes/Show', [
            'orden'       => $orden,
            'can'         => ['reportarAvance'=>$canReportar, 'asignar_tl'=>$canAsignar],
            'teamLeaders' => $teamLeaders,
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

        $filters = [
            'estatus'  => $req->string('estatus')->toString(),
            'calidad'  => $req->string('calidad')->toString(),
            'servicio' => $req->integer('servicio') ?: null,
            'desde'    => $req->date('desde'),
            'hasta'    => $req->date('hasta'),
            'id'       => $req->integer('id') ?: null,
        ];

        $q = Orden::with(['servicio','centro','teamLeader','solicitud'])
            ->when(!$u->hasAnyRole(['admin','facturacion']), fn($qq)=>$qq->where('id_centrotrabajo',$u->centro_trabajo_id))
            ->when($u->hasRole('team_leader'), fn($qq)=>$qq->where('team_leader_id',$u->id))
            ->when($u->hasRole('cliente'), fn($qq)=>$qq->whereHas('solicitud', fn($w)=>$w->where('id_cliente',$u->id)))
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
            return [
                'id' => $o->id,
                'estatus' => $o->estatus,
                'calidad_resultado' => $o->calidad_resultado,
                'created_at' => $o->created_at,
                'servicio' => ['nombre' => $o->servicio?->nombre],
                'centro'   => ['nombre' => $o->centro?->nombre],
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
        $u = auth()->user();
        if ($u->hasAnyRole(['admin','facturacion'])) return;
        // 👇 Requiere MISMO centro para todos los demás roles
        if ((int)$u->centro_trabajo_id !== $centroId) abort(403);
        // Si es TL, solo su propia OT
        if ($orden && $u->hasRole('team_leader') && $orden->team_leader_id !== $u->id) abort(403);
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
