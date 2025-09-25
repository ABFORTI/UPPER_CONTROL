<?php

namespace App\Http\Controllers;

use App\Models\CentroTrabajo;
use App\Models\Solicitud;
use App\Models\ServicioEmpresa;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use App\Models\SolicitudTamano;
use Illuminate\Support\Facades\Auth;
use App\Domain\Servicios\PricingService;

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
            'data' => $q->with(['servicio','centro','cliente'])->paginate(10)->withQueryString(),
            'filters' => $req->only(['estatus','servicio','folio','desde','hasta']),
            'servicios'=> ServicioEmpresa::select('id','nombre')->orderBy('nombre')->get(),
            'urls' => ['index' => route('solicitudes.index')],
            ]);
    }

    public function create()
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        $servicios = \App\Models\ServicioEmpresa::select('id','nombre','usa_tamanos')
                        ->orderBy('nombre')->get();

        // Construir mapa de precios por servicio para el centro del usuario
        $precios = [];
        if ($u) {
            $ids = $servicios->pluck('id')->all();
            $scs = \App\Models\ServicioCentro::with('tamanos')
                ->where('id_centrotrabajo', $u->centro_trabajo_id)
                ->whereIn('id_servicio', $ids)
                ->get();
            foreach ($scs as $sc) {
                $precios[$sc->id_servicio] = [
                    'precio_base' => (float)($sc->precio_base ?? 0),
                    'tamanos' => [
                        'chico'   => optional($sc->tamanos->firstWhere('tamano','chico'))->precio,
                        'mediano' => optional($sc->tamanos->firstWhere('tamano','mediano'))->precio,
                        'grande'  => optional($sc->tamanos->firstWhere('tamano','grande'))->precio,
                        'jumbo'   => optional($sc->tamanos->firstWhere('tamano','jumbo'))->precio,
                    ],
                ];
            }
        }

        return Inertia::render('Solicitudes/Create', [
            'servicios' => $servicios,
            'precios'   => $precios,
            'iva'       => 0.16,
            'urls' => ['store' => route('solicitudes.store')],
        ]);
    }

    public function store(Request $req)
    {
        $serv = ServicioEmpresa::findOrFail($req->id_servicio);
        $u    = $req->user();

    if ($serv->usa_tamanos) {
            // Normaliza payload y valida total
            $t = $req->input('tamanos');
            if (is_string($t)) {
                $t = json_decode($t, true) ?: [];
            }

            $chico   = (int)($t['chico']   ?? 0);
            $mediano = (int)($t['mediano'] ?? 0);
            $grande  = (int)($t['grande']  ?? 0);
            $jumbo   = (int)($t['jumbo']   ?? 0);

            $total   = $chico + $mediano + $grande + $jumbo;

            if ($total <= 0) {
                return back()
                    ->withErrors(['tamanos' => 'Debes capturar al menos 1 pieza.'])
                    ->withInput();
            }

            // Calcula importes
            $pricing = app(\App\Domain\Servicios\PricingService::class);
            $pu = [
                'chico'  => (float)$pricing->precioUnitario($u->centro_trabajo_id, $serv->id, 'chico'),
                'mediano'=> (float)$pricing->precioUnitario($u->centro_trabajo_id, $serv->id, 'mediano'),
                'grande' => (float)$pricing->precioUnitario($u->centro_trabajo_id, $serv->id, 'grande'),
                'jumbo'  => (float)$pricing->precioUnitario($u->centro_trabajo_id, $serv->id, 'jumbo'),
            ];
            $subtotal = ($chico*$pu['chico']) + ($mediano*$pu['mediano']) + ($grande*$pu['grande']) + ($jumbo*$pu['jumbo']);
            $ivaRate = 0.16; $iva = $subtotal*$ivaRate; $totalImporte = $subtotal+$iva;

            // Guarda solicitud + desglose por tamaño en transacción
            $sol = DB::transaction(function () use ($req, $serv, $u, $chico, $mediano, $grande, $jumbo, $total, $subtotal, $iva, $totalImporte) {
                $sol = Solicitud::create([
                    'folio'            => $this->generarFolio($u->centro_trabajo_id),
                    'id_cliente'       => $u->id,
                    'id_centrotrabajo' => $u->centro_trabajo_id,
                    'id_servicio'      => $serv->id,
                    'descripcion'      => $req->descripcion,
                    'cantidad'         => $total,
                    'subtotal'         => $subtotal,
                    'iva'              => $iva,
                    'total'            => $totalImporte,
                    'tamanos_json'     => json_encode(['chico'=>$chico,'mediano'=>$mediano,'grande'=>$grande,'jumbo'=>$jumbo]),
                    'notas'            => $req->notas,
                    'estatus'          => 'pendiente',
                ]);

                $rows = [];
                foreach (['chico'=>$chico, 'mediano'=>$mediano, 'grande'=>$grande, 'jumbo'=>$jumbo] as $tam => $cant) {
                    if ($cant > 0) {
                        $rows[] = [
                            'id_solicitud' => $sol->id,
                            'tamano'       => $tam,
                            'cantidad'     => $cant,
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ];
                    }
                }
                if ($rows) {
                    DB::table('solicitud_tamanos')->insert($rows);
                }

                return $sol;
            });
        } else {
            $req->validate([
                'cantidad' => ['required','integer','min:1'],
            ]);

            $pricing = app(\App\Domain\Servicios\PricingService::class);
            $pu = (float)$pricing->precioUnitario($req->user()->centro_trabajo_id, $serv->id, null);
            $subtotal = $pu * (int)$req->cantidad;
            $ivaRate = 0.16; $iva = $subtotal*$ivaRate; $totalImporte = $subtotal+$iva;

            $sol = Solicitud::create([
                'folio'            => $this->generarFolio($req->user()->centro_trabajo_id), // 👈 genera folio
                'id_cliente'       => $req->user()->id,
                'id_centrotrabajo' => $req->user()->centro_trabajo_id,
                'id_servicio'      => $serv->id,
                'descripcion'      => $req->descripcion,
                'cantidad'         => (int)$req->cantidad,
                'subtotal'         => $subtotal,
                'iva'              => $iva,
                'total'            => $totalImporte,
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

    $solicitud->update(['estatus'=>'aprobada','aprobada_por'=>Auth::id(),'aprobada_at'=>now()]);
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

    $solicitud->update(['estatus'=>'rechazada','aprobada_por'=>Auth::id(),'aprobada_at'=>now()]);
        $this->act('solicitudes')
            ->performedOn($solicitud)
            ->event('rechazar')
            ->withProperties(['resultado' => 'rechazada', 'motivo' => $req->input('motivo')])
            ->log("Solicitud {$solicitud->folio} rechazada");

        return back()->with('ok','Solicitud rechazada');
    }

    private function authorizeCentro(int $centroId): void
    {
        $u = Auth::user();
        if ($u instanceof \App\Models\User && $u->hasAnyRole(['admin','facturacion'])) return;
        if (!($u instanceof \App\Models\User) || ((int)$u->centro_trabajo_id !== $centroId && !$u->hasRole('calidad'))) {
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
    $solicitud->load(['cliente','servicio','centro','archivos','tamanos','ordenes']);
        $user = $req->user();

        $canAprobar = $user->hasAnyRole(['coordinador','admin'])
            && $solicitud->estatus === 'pendiente';

        // Cotización para visualizar precios con IVA
        $cotizacion = $this->buildCotizacion($solicitud);

        return Inertia::render('Solicitudes/Show', [
            'solicitud' => $solicitud->toArray(),
            'can' => [
                'aprobar'  => $canAprobar,
                'rechazar' => $canAprobar,
            ],
            'urls' => [
                'aprobar'    => route('solicitudes.aprobar', $solicitud),
                'rechazar'   => route('solicitudes.rechazar', $solicitud),
                'generar_ot' => route('ordenes.createFromSolicitud', $solicitud),
            ],
            'flags' => [
                'tiene_ot' => $solicitud->ordenes->count() > 0,
            ],
            'cotizacion' => $cotizacion,
        ]);
    }

    private function act(string $log)
    {
        return app(\Spatie\Activitylog\ActivityLogger::class)->useLog($log);
    }

    private function buildCotizacion(Solicitud $solicitud): array
    {
        $ivaRate = 0.16;
        $pricing = app(PricingService::class);
        $lines = [];
        $subtotal = 0.0;

        // Si hay desglose por tamaños, calcular por línea
        if ($solicitud->relationLoaded('tamanos') && $solicitud->tamanos && $solicitud->tamanos->count() > 0) {
            foreach ($solicitud->tamanos as $t) {
                $tam = (string)($t->tamano ?? '');
                $cant = (int)($t->cantidad ?? 0);
                if ($cant <= 0) continue;
                $pu = (float)$pricing->precioUnitario($solicitud->id_centrotrabajo, $solicitud->id_servicio, $tam);
                $sub = $pu * $cant;
                $subtotal += $sub;
                $lines[] = [
                    'label'    => ucfirst($tam),
                    'tamano'   => $tam,
                    'cantidad' => $cant,
                    'pu'       => $pu,
                    'subtotal' => $sub,
                ];
            }
            $mode = 'tamanos';
        } else {
            // Servicio por pieza
            $pu = (float)$pricing->precioUnitario($solicitud->id_centrotrabajo, $solicitud->id_servicio, null);
            $subtotal = $pu * (int)($solicitud->cantidad ?? 0);
            $lines = [
                [
                    'label'    => 'Pieza',
                    'tamano'   => null,
                    'cantidad' => (int)($solicitud->cantidad ?? 0),
                    'pu'       => $pu,
                    'subtotal' => $subtotal,
                ]
            ];
            $mode = 'pieza';
        }

        $iva = $subtotal * $ivaRate;
        $total = $subtotal + $iva;
        return [
            'mode'      => $mode,
            'lines'     => $lines,
            'subtotal'  => $subtotal,
            'iva_rate'  => $ivaRate,
            'iva'       => $iva,
            'total'     => $total,
        ];
    }
}
