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
        $isCliente = method_exists($u, 'hasRole') ? $u->hasRole('cliente') : false;
        $isClienteCentro = method_exists($u, 'hasRole') ? $u->hasRole('cliente_centro') : false;

        $filters = [
            'estatus'  => $req->string('estatus')->toString(),
            'servicio' => $req->integer('servicio') ?: null,
            'folio'    => $req->string('folio')->toString(),
            'desde'    => $req->date('desde'),
            'hasta'    => $req->date('hasta'),
            'year'     => $req->integer('year') ?: null,
            'week'     => $req->integer('week') ?: null,
        ];

        $q = Solicitud::with(['servicio','centro'])
            ->when(!$u->hasAnyRole(['admin','facturacion','calidad']),
                fn($qq) => $qq->where('id_centrotrabajo', $u->centro_trabajo_id))
            ->when($u->hasAnyRole(['facturacion','calidad']) && !$u->hasRole('admin'), function($qq) use ($u) {
                $ids = $this->allowedCentroIds($u);
                if (!empty($ids)) { $qq->whereIn('id_centrotrabajo', $ids); }
            })
            ->when($isCliente && !$isClienteCentro, fn($qq)=>$qq->where('id_cliente',$u->id))
            ->when($filters['estatus'],  fn($qq,$v)=>$qq->where('estatus',$v))
            ->when($filters['servicio'], fn($qq,$v)=>$qq->where('id_servicio',$v))
            ->when($filters['folio'],    fn($qq,$v)=>$qq->where('folio','like',"%{$v}%"))
            ->when($filters['desde'] && $filters['hasta'], fn($qq)=>$qq->whereBetween(
                'created_at', [$filters['desde']->startOfDay(), $filters['hasta']->endOfDay()]
            ))
            ->when($filters['year'] && $filters['week'], function($qq) use ($filters) {
                $qq->whereRaw('YEAR(created_at) = ? AND WEEK(created_at, 1) = ?', [$filters['year'], $filters['week']]);
            })
            ->when($filters['year'] && !$filters['week'], function($qq) use ($filters) {
                $qq->whereYear('created_at', $filters['year']);
            })
            ->orderByDesc('id');

        $data = $q->paginate(10)->withQueryString();

        return Inertia::render('Solicitudes/Index', [
            'data' => $q->with(['servicio','centro','cliente','area','archivos'])->paginate(10)->withQueryString(),
            'filters' => $req->only(['estatus','servicio','folio','desde','hasta','year','week']),
            'servicios'=> ServicioEmpresa::select('id','nombre')->orderBy('nombre')->get(),
            'urls' => ['index' => route('solicitudes.index')],
            ]);
    }

    public function create()
    {
        /** @var \App\Models\User $u */
        $u = \Illuminate\Support\Facades\Auth::user();
        
        // Verificar bloqueo por OTs vencidas sin autorizar
        $bloqueo = $this->verificarBloqueoOTsVencidas($u);
        if ($bloqueo) {
            return Inertia::render('Solicitudes/Bloqueada', [
                'mensaje' => $bloqueo['mensaje'],
                'ordenes_vencidas' => $bloqueo['ordenes'],
                'tiempo_limite' => $bloqueo['tiempo_limite_texto'],
            ]);
        }
        
        $servicios = \App\Models\ServicioEmpresa::select('id','nombre','usa_tamanos')
            ->orderBy('nombre')->get();

        $canChooseCentro = $u && $u->hasAnyRole(['admin','facturacion','calidad']);
        $selectedCentroId = (int)($u->centro_trabajo_id ?? 0) ?: null;

        $centros = collect();
        $precios = [];
        $preciosPorCentro = [];

        if ($canChooseCentro) {
            // Admin: siempre todos los centros; otros roles: solo asignados (o todos si no hay asignados)
            if ($u->hasRole('admin')) {
                $centros = \App\Models\CentroTrabajo::select('id','nombre','prefijo')->orderBy('nombre')->get();
            } else {
                $assignedCentros = $u->centros()
                    ->select('centros_trabajo.id','centros_trabajo.nombre','centros_trabajo.prefijo')
                    ->orderBy('centros_trabajo.nombre')
                    ->get();
                $centros = $assignedCentros->isNotEmpty()
                    ? $assignedCentros
                    : \App\Models\CentroTrabajo::select('id','nombre','prefijo')->orderBy('nombre')->get();
            }

            // Construye mapa de precios por centro y servicio
            $idsServicios = $servicios->pluck('id')->all();
            $idsCentros = $centros->pluck('id')->all();
            $scs = \App\Models\ServicioCentro::with('tamanos')
                ->whereIn('id_centrotrabajo', $idsCentros)
                ->whereIn('id_servicio', $idsServicios)
                ->get();
            foreach ($scs as $sc) {
                $preciosPorCentro[$sc->id_centrotrabajo][$sc->id_servicio] = [
                    'precio_base' => (float)($sc->precio_base ?? 0),
                    'tamanos' => [
                        'chico'   => optional($sc->tamanos->firstWhere('tamano','chico'))->precio,
                        'mediano' => optional($sc->tamanos->firstWhere('tamano','mediano'))->precio,
                        'grande'  => optional($sc->tamanos->firstWhere('tamano','grande'))->precio,
                        'jumbo'   => optional($sc->tamanos->firstWhere('tamano','jumbo'))->precio,
                    ],
                ];
            }
        } else {
            // Construir mapa de precios por servicio solo para el centro del usuario
            if ($u && $u->centro_trabajo_id) {
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
        }

        return Inertia::render('Solicitudes/Create', [
            'servicios'         => $servicios,
            'precios'           => $precios,
            'preciosPorCentro'  => $preciosPorCentro,
            'centros'           => $centros,
            'canChooseCentro'   => $canChooseCentro,
            'selectedCentroId'  => $selectedCentroId,
            'iva'               => 0.16,
            'urls' => ['store' => route('solicitudes.store')],
            'areas'             => $u->centro_trabajo_id 
                ? \App\Models\Area::where('id_centrotrabajo', $u->centro_trabajo_id)->activas()->orderBy('nombre')->get()
                : [],
            'areasPorCentro'    => $canChooseCentro 
                ? \App\Models\Area::activas()->get()->groupBy('id_centrotrabajo')->map(function($items) {
                    return $items->sortBy('nombre')->values();
                  })
                : [],
        ]);
    }

    public function store(Request $req)
    {
        $u = $req->user();
        
        // Verificar bloqueo por OTs vencidas sin autorizar
        $bloqueo = $this->verificarBloqueoOTsVencidas($u);
        if ($bloqueo) {
            return back()->withErrors([
                'bloqueo' => $bloqueo['mensaje']
            ])->withInput();
        }
        
        $serv = ServicioEmpresa::findOrFail($req->id_servicio);

        // Determinar centro a usar
        $canChooseCentro = $u && $u->hasAnyRole(['admin','facturacion','calidad']);
        $centroId = null;
        if ($canChooseCentro) {
            $req->validate(['id_centrotrabajo' => ['required','integer','exists:centros_trabajo,id']]);
            $centroId = (int)$req->input('id_centrotrabajo');
        } else {
            $centroId = (int)($u->centro_trabajo_id ?? 0);
        }

        if (!$centroId) {
            return back()->withErrors([
                'centro' => 'No se pudo determinar el centro de trabajo para la solicitud.'
            ])->withInput();
        }

        // Para roles no privilegiados, deben tener centro_trabajo_id asignado
        if (!($u && $u->hasAnyRole(['admin','facturacion','calidad'])) && (!$u || !$u->centro_trabajo_id)) {
            return back()->withErrors([
                'centro' => 'Tu usuario no tiene un centro de trabajo asignado. Pide a un administrador que lo configure.'
            ])->withInput();
        }

        // Usar un pequeño retry para evitar colisiones de folio bajo concurrencia
        $attempts = 0; $maxAttempts = 3; $lastException = null;
        while ($attempts < $maxAttempts) {
            try {
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
                'chico'  => (float)$pricing->precioUnitario($centroId, $serv->id, 'chico'),
                'mediano'=> (float)$pricing->precioUnitario($centroId, $serv->id, 'mediano'),
                'grande' => (float)$pricing->precioUnitario($centroId, $serv->id, 'grande'),
                'jumbo'  => (float)$pricing->precioUnitario($centroId, $serv->id, 'jumbo'),
            ];
            $subtotal = ($chico*$pu['chico']) + ($mediano*$pu['mediano']) + ($grande*$pu['grande']) + ($jumbo*$pu['jumbo']);
            $ivaRate = 0.16; $iva = $subtotal*$ivaRate; $totalImporte = $subtotal+$iva;

            // Guarda solicitud + desglose por tamaño en transacción
            $sol = DB::transaction(function () use ($req, $serv, $u, $centroId, $chico, $mediano, $grande, $jumbo, $total, $subtotal, $iva, $totalImporte) {
                $sol = Solicitud::create([
                    'folio'            => $this->generarFolio($centroId),
                    'id_cliente'       => $u->id,
                    'id_centrotrabajo' => $centroId,
                    'id_servicio'      => $serv->id,
                    'descripcion'      => $req->descripcion,
                    'id_area'          => $req->id_area,
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
            $pu = (float)$pricing->precioUnitario($centroId, $serv->id, null);
            $subtotal = $pu * (int)$req->cantidad;
            $ivaRate = 0.16; $iva = $subtotal*$ivaRate; $totalImporte = $subtotal+$iva;

                    $sol = Solicitud::create([
                        'folio'            => $this->generarFolio($centroId), // 👈 genera folio
                        'id_cliente'       => $req->user()->id,
                        'id_centrotrabajo' => $centroId,
                        'id_servicio'      => $serv->id,
                        'descripcion'      => $req->descripcion,
                        'id_area'          => $req->id_area,
                        'cantidad'         => (int)$req->cantidad,
                        'subtotal'         => $subtotal,
                        'iva'              => $iva,
                        'total'            => $totalImporte,
                        'notas'            => $req->notas,
                        'estatus'          => 'pendiente',
                    ]);
                }

                // Si todo fue bien, salimos del bucle
                break;
            } catch (\Throwable $ex) {
                // Maneja colisiones de UNIQUE tanto como UniqueConstraintViolationException como QueryException (23000)
                $isUnique = $ex instanceof \Illuminate\Database\UniqueConstraintViolationException
                    || ($ex instanceof \Illuminate\Database\QueryException && (string)$ex->getCode() === '23000');
                if ($isUnique) {
                    $lastException = $ex;
                    $attempts++;
                    usleep(120000); // 120ms
                    continue;
                }
                throw $ex; // otros errores: propagar
            }
        }
        if (!isset($sol)) {
            throw $lastException ?? new \RuntimeException('No fue posible generar un folio único');
        }

        // Manejar archivos adjuntos
        if ($req->hasFile('archivos')) {
            $archivos = $req->file('archivos');
            // Filtrar archivos válidos (a veces vienen elementos vacíos en el array)
            if (is_array($archivos)) {
                foreach ($archivos as $file) {
                    if ($file && $file->isValid()) {
                        $path = $file->store('solicitudes/' . $sol->id, 'public');
                        $sol->archivos()->create([
                            'path'             => $path,
                            'nombre_original'  => $file->getClientOriginalName(),
                            'mime'             => $file->getClientMimeType(),
                            'size'             => $file->getSize(),
                            'subtipo'          => 'adjunto',
                        ]);
                    }
                }
            }
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
        if (!($u instanceof \App\Models\User)) abort(403);
        if ($u->hasRole('admin')) return; // admin full acceso
        $ids = $this->allowedCentroIds($u);
        if (empty($ids) || !in_array((int)$centroId, array_map('intval', $ids), true)) abort(403);
    }

    private function allowedCentroIds(\App\Models\User $u): array
    {
        if ($u->hasRole('admin')) return [];
        $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v)=>(int)$v)->all();
        $primary = (int)($u->centro_trabajo_id ?? 0);
        if ($primary) $ids[] = $primary;
        return array_values(array_unique(array_filter($ids)));
    }

    /** Folio tipo ABC-YYYYMM-0001: usa prefijo del centro si existe; si no, deriva de nombre */
    private function generarFolio(int $centroId): string
    {
        $centro = CentroTrabajo::find($centroId);
        // Prefiere prefijo definido (p.ej. UMX, UGDL). Si no existe, deriva de nombre.
        $prefijo = $centro?->prefijo
            ?? ($centro && $centro->nombre
                ? strtoupper(substr(preg_replace('/[^a-z]/i', '', $centro->nombre), 0, 3))
                : 'UPR');

        $prefijo = strtoupper(substr($prefijo, 0, 10)); // por si acaso más largo
        $yyyymm  = now()->format('Ym');

        $base = $prefijo . '-' . $yyyymm . '-';
        // Buscar el último folio que coincida y extraer el consecutivo de 4 dígitos
        // Intentar minimizar colisiones: leer último folio con lock (si dentro de transacción)
        $lastFolio = Solicitud::where('folio', 'like', $base . '%')
            ->orderByDesc('folio')
            ->lockForUpdate()
            ->value('folio');

        $seq = 1;
        if (is_string($lastFolio) && preg_match('/-(\d{4})$/', $lastFolio, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('%s%04d', $base, $seq);
    }

    public function show(Request $req, Solicitud $solicitud)
    {
    $solicitud->load(['cliente','servicio','centro','area','archivos','tamanos','ordenes']);
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

    /**
     * Verifica si hay OTs completadas sin autorizar que hayan excedido el tiempo límite.
     * Si encuentra alguna en el centro del usuario, bloquea la creación de nuevas solicitudes.
     * 
     * @param \App\Models\User $user
     * @return array|null ['mensaje' => string, 'ordenes' => array, 'tiempo_limite_texto' => string] o null si no hay bloqueo
     */
    private function verificarBloqueoOTsVencidas($user): ?array
    {
        // Solo aplicar a usuarios tipo cliente (no admin/coordinador/etc)
        if (!$user || $user->hasAnyRole(['admin', 'coordinador', 'facturacion', 'calidad'])) {
            return null;
        }

        // Verificar si el feature está habilitado
        if (!config('business.bloquear_solicitudes_por_ots_vencidas', true)) {
            return null;
        }

        $centroId = (int)($user->centro_trabajo_id ?? 0);
        if (!$centroId) {
            return null; // Sin centro asignado, no aplicar bloqueo
        }

        // Obtener tiempo límite en minutos desde config
        // PARA PRUEBAS: 1 minuto
        // PARA PRODUCCIÓN: 4320 minutos (72 horas)
        $timeoutMinutos = (int)config('business.ot_autorizacion_timeout_minutos', 1);
        $limiteTimestamp = now()->subMinutes($timeoutMinutos);

        // Buscar OTs completadas sin autorizar que hayan excedido el tiempo
        $otsVencidas = \App\Models\Orden::where('id_centrotrabajo', $centroId)
            ->where('estatus', 'completada')
            ->where('updated_at', '<=', $limiteTimestamp) // completada hace más de X tiempo
            ->whereDoesntHave('aprobaciones', function($q) {
                $q->where('tipo', 'cliente')->where('resultado', 'autorizado');
            })
            ->with('solicitud:id,folio')
            ->orderBy('updated_at', 'asc')
            ->get(['id', 'updated_at', 'id_solicitud']);

        if ($otsVencidas->isEmpty()) {
            return null; // No hay bloqueo
        }

        // Construir mensaje de bloqueo
        $tiempoTexto = $timeoutMinutos >= 60 
            ? round($timeoutMinutos / 60) . ' hora(s)'
            : $timeoutMinutos . ' minuto(s)';

        $ordenesDetalle = $otsVencidas->map(function($ot) use ($timeoutMinutos) {
            $transcurrido = now()->diffInMinutes($ot->updated_at);
            $folio = $ot->solicitud ? $ot->solicitud->folio : 'OT-' . $ot->id;
            return [
                'id' => $ot->id,
                'folio' => $folio,
                'completada_hace' => $this->formatearTiempo($transcurrido),
                'url' => route('ordenes.show', $ot->id),
            ];
        })->toArray();

        $mensaje = sprintf(
            'No puedes crear nuevas solicitudes. Hay %d orden(es) de trabajo completada(s) hace más de %s sin autorización del cliente. Por favor, autoriza las órdenes pendientes antes de continuar.',
            $otsVencidas->count(),
            $tiempoTexto
        );

        return [
            'mensaje' => $mensaje,
            'ordenes' => $ordenesDetalle,
            'tiempo_limite_texto' => $tiempoTexto,
        ];
    }

    /**
     * Formatea minutos en texto legible
     */
    private function formatearTiempo(int $minutos): string
    {
        if ($minutos < 60) {
            return $minutos . ' minuto(s)';
        }
        if ($minutos < 1440) { // < 24h
            $horas = round($minutos / 60, 1);
            return $horas . ' hora(s)';
        }
        $dias = round($minutos / 1440, 1);
        return $dias . ' día(s)';
    }
}
