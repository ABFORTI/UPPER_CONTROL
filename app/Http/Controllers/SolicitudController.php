<?php

namespace App\Http\Controllers;

use App\Models\CentroTrabajo;
use App\Models\Solicitud;
use App\Models\ServicioEmpresa;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\Notifier;

class SolicitudController extends Controller
{
    public function index(Request $req)
    {
        $u = $req->user();

        $filters = [
            'estatus'  => $req->string('estatus')->toString(),
            'servicio' => $req->integer('servicio') ?: null,
            'folio'    => $req->string('folio')->toString(),
            'desde'    => $req->date('desde'),
            'hasta'    => $req->date('hasta'),
        ];

        $q = Solicitud::with(['servicio','centro'])
            ->when(!$u->hasAnyRole(['admin','facturacion','calidad']),
                fn($qq) => $qq->where('id_centrotrabajo', $u->centro_trabajo_id))
            ->when($filters['estatus'],  fn($qq,$v)=>$qq->where('estatus',$v))
            ->when($filters['servicio'], fn($qq,$v)=>$qq->where('id_servicio',$v))
            ->when($filters['folio'],    fn($qq,$v)=>$qq->where('folio','like',"%{$v}%"))
            ->when($filters['desde'] && $filters['hasta'], fn($qq)=>$qq->whereBetween(
                'created_at', [$filters['desde']->startOfDay(), $filters['hasta']->endOfDay()]
            ))
            ->orderByDesc('id');

        $data = $q->paginate(10)->withQueryString();

        return Inertia::render('Solicitudes/Index', [
            'data'      => $data,
            'filters'   => $req->only(['estatus','servicio','folio','desde','hasta']),
            'servicios' => ServicioEmpresa::select('id','nombre')->orderBy('nombre')->get(),
            'urls'      => ['index' => route('solicitudes.index')],
        ]);
    }

    public function create()
    {
        return Inertia::render('Solicitudes/Create', [
            // 👇 importante: enviamos usa_tamanos para mostrar los campos por tamaño
            'servicios' => ServicioEmpresa::select('id','nombre','usa_tamanos')->orderBy('nombre')->get(),
            'urls'      => ['store' => route('solicitudes.store')], // URL absoluta (respeta tu subcarpeta)
        ]);
    }

    public function store(Request $req)
    {
        $serv = ServicioEmpresa::findOrFail($req->id_servicio);

        if ($serv->usa_tamanos) {
            $tamanos = $req->input('tamanos');
            if (is_string($tamanos)) {
                $tamanos = json_decode($tamanos, true) ?: [];
            }

            $chico   = (int)($tamanos['chico']   ?? 0);
            $mediano = (int)($tamanos['mediano'] ?? 0);
            $grande  = (int)($tamanos['grande']  ?? 0);
            $total   = $chico + $mediano + $grande;

            if ($total <= 0) {
                return back()
                    ->withErrors(['tamanos' => 'Debes capturar al menos 1 pieza.'])
                    ->withInput();
            }

            $sol = Solicitud::create([
                'folio'            => $this->generarFolio($req->user()->centro_trabajo_id), // 👈 genera folio
                'id_cliente'       => $req->user()->id,
                'id_centrotrabajo' => $req->user()->centro_trabajo_id,
                'id_servicio'      => $serv->id,
                'descripcion'      => $req->descripcion,
                'cantidad'         => $total,
                'tamanos_json'     => json_encode(['chico'=>$chico,'mediano'=>$mediano,'grande'=>$grande]),
                'notas'            => $req->notas,
                'estatus'          => 'pendiente',
            ]);
        } else {
            $req->validate([
                'cantidad' => ['required','integer','min:1'],
            ]);

            $sol = Solicitud::create([
                'folio'            => $this->generarFolio($req->user()->centro_trabajo_id), // 👈 genera folio
                'id_cliente'       => $req->user()->id,
                'id_centrotrabajo' => $req->user()->centro_trabajo_id,
                'id_servicio'      => $serv->id,
                'descripcion'      => $req->descripcion,
                'cantidad'         => (int)$req->cantidad,
                'notas'            => $req->notas,
                'estatus'          => 'pendiente',
            ]);
        }


        // Notificar a coordinador del centro
        Notifier::toRoleInCentro(
            'coordinador',
            $sol->id_centrotrabajo,
            'Nueva solicitud',
            "El cliente creó la solicitud {$sol->folio} ({$sol->descripcion}).",
            route('solicitudes.show',$sol->id)
        );

        // (Adjuntos opcionales…)

        return redirect()
            ->route('solicitudes.show', $sol->id)
            ->with('ok','Solicitud creada');
    }

    public function aprobar(Solicitud $solicitud)
    {
        $this->authorize('aprobar', $solicitud);
        $this->authorizeCentro($solicitud->id_centrotrabajo);

        $solicitud->update(['estatus'=>'aprobada','aprobada_por'=>auth()->id(),'aprobada_at'=>now()]);
        $this->act('solicitudes')
            ->performedOn($solicitud)
            ->event('aprobar')
            ->withProperties(['resultado' => 'aprobada'])
            ->log("Solicitud {$solicitud->folio} aprobada");

        Notifier::toUser(
            $solicitud->id_cliente,
            'Solicitud aprobada',
            "Tu solicitud {$solicitud->folio} fue aprobada.",
            route('solicitudes.show',$solicitud->id)
        );
        return back()->with('ok','Solicitud aprobada');
    }

    public function rechazar(Solicitud $solicitud, Request $req)
    {
        $this->authorize('aprobar', $solicitud);
        $this->authorizeCentro($solicitud->id_centrotrabajo);

        $solicitud->update(['estatus'=>'rechazada','aprobada_por'=>auth()->id(),'aprobada_at'=>now()]);
        $this->act('solicitudes')
            ->performedOn($solicitud)
            ->event('rechazar')
            ->withProperties(['resultado' => 'rechazada', 'motivo' => $req->input('motivo')])
            ->log("Solicitud {$solicitud->folio} rechazada");

        return back()->with('ok','Solicitud rechazada');
    }

    private function authorizeCentro(int $centroId): void
    {
        $u = auth()->user();
        if ($u->hasAnyRole(['admin','facturacion'])) return;
        if ((int)$u->centro_trabajo_id !== $centroId && !$u->hasRole('calidad')) {
            abort(403);
        }
    }

    /** Folio tipo ABC-YYYYMM-0001 (usa código/clave/nombre del centro) */
    private function generarFolio(int $centroId): string
    {
        $centro = CentroTrabajo::find($centroId);
        $prefijo = $centro?->codigo
            ?? ($centro?->clave ?? null)
            ?? ($centro && $centro->nombre
                ? strtoupper(substr(preg_replace('/[^a-z]/i','',$centro->nombre),0,3))
                : 'UPR');

        $prefijo = strtoupper(substr($prefijo, 0, 3));
        $yyyymm  = now()->format('Ym');

        $seq = Solicitud::where('id_centrotrabajo', $centroId)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count() + 1;

        return sprintf('%s-%s-%04d', $prefijo, $yyyymm, $seq);
    }

    public function show(Request $req, Solicitud $solicitud)
    {
        $solicitud->load(['cliente','servicio','centro','archivos']);
        $user = $req->user();

        $canAprobar = $user->hasAnyRole(['coordinador','admin'])
            && $solicitud->estatus === 'pendiente';

        return Inertia::render('Solicitudes/Show', [
            'solicitud' => $solicitud->toArray(),
            'can' => [
                'aprobar'  => $canAprobar,
                'rechazar' => $canAprobar,
            ],
        ]);
    }

    private function act(string $log)
    {
        return app(\Spatie\Activitylog\ActivityLogger::class)->useLog($log);
    }
}
