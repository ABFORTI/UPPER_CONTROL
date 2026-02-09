<?php

namespace App\Http\Controllers;

use App\Models\CentroTrabajo;
use App\Models\Solicitud;
use App\Models\ServicioEmpresa;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use App\Models\SolicitudTamano;
use Illuminate\Support\Facades\Auth;
use App\Domain\Servicios\PricingService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SolicitudController extends Controller
{
    public function index(Request $req)
    {
        $u = $req->user();
        $isCliente = method_exists($u, 'hasRole') ? $u->hasRole('Cliente_Supervisor') : false;
        $isClienteCentro = method_exists($u, 'hasRole') ? $u->hasRole('Cliente_Gerente') : false;

        $filters = [
            'estatus'  => $req->string('estatus')->toString(),
            'servicio' => $req->integer('servicio') ?: null,
            'folio'    => $req->string('folio')->toString(),
            'desde'    => $req->date('desde'),
            'hasta'    => $req->date('hasta'),
            'year'     => $req->integer('year') ?: null,
            'week'     => $req->integer('week') ?: null,
        ];

    $q = Solicitud::with(['servicio','centro','centroCosto','marca'])
            ->when(!$u->hasAnyRole(['admin','facturacion','calidad','control','comercial','gerente_upper']),
                fn($qq) => $qq->where('id_centrotrabajo', $u->centro_trabajo_id))
            ->when($u->hasAnyRole(['facturacion','calidad','control','comercial','gerente_upper']) && !$u->hasRole('admin'), function($qq) use ($u) {
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

    $paginator = $q->with(['servicio','centro','cliente','area','archivos','centroCosto','marca'])->paginate(10)->withQueryString();
        // Transform each item to include a formatted 'fecha' field expected by the frontend
        $paginator->getCollection()->transform(function($s) {
            // Mostrar exactamente lo guardado en BD (sin conversión de huso). Se formatea una sola vez a 'Y-m-d H:i'.
            $raw = $s->getRawOriginal('created_at');
            $fecha = null; $fechaHumana = null; $fechaIso = null;
            if ($raw) {
                try {
                    $dt = \Carbon\Carbon::parse($raw); // sin tz explícita
                    $fecha = $dt->format('Y-m-d H:i');
                    $fechaHumana = $dt->diffForHumans();
                    $fechaIso = $dt->toIso8601String();
                } catch (\Throwable $e) {
                    $fecha = substr($raw, 0, 16); // fallback: yyyy-mm-dd hh:mm
                }
            }

            return [
                'id' => $s->id,
                'folio' => $s->folio,
                'producto' => $s->descripcion ?? null,
                'cliente' => ['name' => $s->cliente?->name ?? null],
                'servicio' => ['nombre' => $s->servicio?->nombre ?? null],
                'centro' => ['nombre' => $s->centro?->nombre ?? null],
                'centroCosto' => ['nombre' => $s->centroCosto?->nombre ?? null],
                'marca' => ['nombre' => $s->marca?->nombre ?? null],
                'area' => ['nombre' => $s->area?->nombre ?? null],
                'cantidad' => $s->cantidad,
                'archivos' => $s->archivos ?? [],
                'estatus' => $s->estatus,
                'fecha' => $fecha,
                'fecha_humana' => $fechaHumana,
                'fecha_iso' => $fechaIso,
                'created_at_raw' => $raw,
            ];
        });

        return Inertia::render('Solicitudes/Index', [
            'data' => $paginator,
            'filters' => $req->only(['estatus','servicio','folio','desde','hasta','year','week']),
            'servicios'=> ServicioEmpresa::select('id','nombre')->orderBy('nombre')->get(),
            'urls' => ['index' => route('solicitudes.index')],
        ]);
    }

    public function create()
    {
        /** @var \App\Models\User $u */
        $u = \Illuminate\Support\Facades\Auth::user();

        // La pantalla de crear solicitud es sólo para clientes (y admin). Evitar que gerente_upper u otros roles creen.
        $this->authorize('create', \App\Models\Solicitud::class);
        
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

    $canChooseCentro = $u && $u->hasAnyRole(['admin','facturacion','calidad','control','comercial']);
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

    // Cargar catálogos adicionales (centrados en existencia de tablas para evitar fallos si faltan migraciones)
    $hasCC = Schema::hasTable('centros_costos');
    $hasMarcas = Schema::hasTable('marcas');

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
            // Centros de costos y Marcas
            'centrosCostos' => ($hasCC && !$canChooseCentro && $u->centro_trabajo_id)
                ? \App\Models\CentroCosto::where('id_centrotrabajo', $u->centro_trabajo_id)->activos()->orderBy('nombre')->get()
                : [],
            'centrosCostosPorCentro' => ($hasCC && $canChooseCentro)
                ? \App\Models\CentroCosto::activos()->get()->groupBy('id_centrotrabajo')->map(function($items){
                    return $items->sortBy('nombre')->values();
                  })
                : [],
            'marcas' => ($hasMarcas && !$canChooseCentro && $u->centro_trabajo_id)
                ? \App\Models\Marca::where('id_centrotrabajo', $u->centro_trabajo_id)->activos()->orderBy('nombre')->get()
                : [],
            'marcasPorCentro' => ($hasMarcas && $canChooseCentro)
                ? \App\Models\Marca::activos()->get()->groupBy('id_centrotrabajo')->map(function($items){
                    return $items->sortBy('nombre')->values();
                  })
                : [],
        ]);
    }

    public function store(Request $req)
    {
        $u = $req->user();

        // Bloquear creación a roles que no deben crear (p.ej. gerente_upper). La policy define el alcance.
        $this->authorize('create', \App\Models\Solicitud::class);
        
        // Verificar bloqueo por OTs vencidas sin autorizar
        $bloqueo = $this->verificarBloqueoOTsVencidas($u);
        if ($bloqueo) {
            return back()->withErrors([
                'bloqueo' => $bloqueo['mensaje']
            ])->withInput();
        }
        
        // NUEVO: Detectar si viene con múltiples servicios
        $esMultipleServicios = $req->has('servicios') && is_array($req->servicios);
        
        // DEBUG TEMPORAL
        \Log::info('📝 Datos recibidos en store', [
            'esMultiple' => $esMultipleServicios,
            'servicios_raw' => $req->servicios,
            'cantidad_servicios' => $esMultipleServicios ? count($req->servicios) : 0,
        ]);
        
        // Validar servicio(s)
        if ($esMultipleServicios) {
            $req->validate([
                'servicios' => ['required', 'array', 'min:1'],
                'servicios.*.id_servicio' => ['required', 'integer', 'exists:servicios_empresa,id'],
                'servicios.*.cantidad' => ['required', 'integer', 'min:1'],
                'cantidad' => ['required', 'integer', 'min:1'],
            ]);
        } else {
            $req->validate([
                'id_servicio' => ['required', 'integer', 'exists:servicios_empresa,id'],
            ]);
        }
        
        // Para modo simple, obtener el servicio
        $serv = $esMultipleServicios ? null : ServicioEmpresa::findOrFail($req->id_servicio);

        // Determinar centro a usar
    $canChooseCentro = $u && $u->hasAnyRole(['admin','facturacion','calidad','control','comercial']);
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
    if (!($u && $u->hasAnyRole(['admin','facturacion','calidad','control','comercial'])) && (!$u || !$u->centro_trabajo_id)) {
            return back()->withErrors([
                'centro' => 'Tu usuario no tiene un centro de trabajo asignado. Pide a un administrador que lo configure.'
            ])->withInput();
        }

        // Validaciones de centro de costo (obligatorio) y marca (opcional) según el centro elegido
        $req->validate([
            'id_centrocosto' => ['required','integer','exists:centros_costos,id'],
            'id_marca' => ['nullable','integer','exists:marcas,id'],
            'id_area' => ['nullable','integer','exists:areas,id'],
        ]);
        $cc = \App\Models\CentroCosto::find($req->id_centrocosto);
        if (!$cc || (int)$cc->id_centrotrabajo !== (int)$centroId) {
            return back()->withErrors(['id_centrocosto' => 'El centro de costos no pertenece al centro seleccionado.'])->withInput();
        }
        $marca = null;
        if ($req->filled('id_marca')) {
            $marca = \App\Models\Marca::find($req->id_marca);
            if (!$marca || (int)$marca->id_centrotrabajo !== (int)$centroId) {
                return back()->withErrors(['id_marca' => 'La marca seleccionada no pertenece al centro seleccionado.'])->withInput();
            }
        }

        $areaId = null;
        if ($req->filled('id_area')) {
            $area = \App\Models\Area::find($req->id_area);
            if (!$area || (int)$area->id_centrotrabajo !== (int)$centroId) {
                return back()->withErrors(['id_area' => 'El área seleccionada no pertenece al centro seleccionado.'])->withInput();
            }
            $areaId = (int)$area->id;
        }

        // Excel origen (subido previamente para precarga): guardar referencia si viene
        $excelStoredName = trim((string) $req->input('excel_stored_name', ''));
        $excelStoredName = $excelStoredName !== '' ? basename($excelStoredName) : null;
        $excelOriginalName = trim((string) $req->input('excel_nombre_original', ''));
        $excelOriginalName = $excelOriginalName !== '' ? basename($excelOriginalName) : null;

        if ($excelStoredName) {
            if (!preg_match('/^[A-Za-z0-9._-]+\.(xlsx|xls)$/i', $excelStoredName)) {
                return back()->withErrors(['excel_stored_name' => 'Nombre de archivo Excel inválido.'])->withInput();
            }
            if (!Storage::exists('solicitudes_excel/' . $excelStoredName)) {
                return back()->withErrors(['excel_stored_name' => 'El archivo Excel asociado no existe o expiró.'])->withInput();
            }
        }

        // NUEVO: Si es modo múltiple servicios, crear UNA solicitud con múltiples servicios asociados
        if ($esMultipleServicios) {
            DB::beginTransaction();
            try {
                \Log::info('🔄 Creando solicitud con múltiples servicios', ['count' => count($req->servicios)]);
                
                // Crear la solicitud principal (sin servicio único)
                $sol = Solicitud::create([
                    'folio'            => $this->generarFolio($centroId),
                    'id_cliente'       => $u->id,
                    'id_centrotrabajo' => $centroId,
                    'id_servicio'      => null, // Ya no usamos este campo para múltiples
                    'descripcion'      => $req->descripcion,
                    'id_centrocosto'   => (int)$req->id_centrocosto,
                    'id_marca'         => $req->filled('id_marca') ? (int)$req->id_marca : null,
                    'id_area'          => $areaId,
                    'cantidad'         => (int)$req->cantidad, // Cantidad total de referencia
                    'subtotal'         => 0, // Se calculará después
                    'iva'              => 0,
                    'total'            => 0,
                    'notas'            => $req->notas,
                    'estatus'          => 'pendiente',
                    'archivo_excel_stored_name' => $excelStoredName,
                    'archivo_excel_nombre_original' => $excelOriginalName,
                    'archivo_excel_subido_por' => $excelStoredName ? (int)$u->id : null,
                    'archivo_excel_subido_at' => $excelStoredName ? now() : null,
                ]);
                
                $pricing = app(\App\Domain\Servicios\PricingService::class);
                
                // Crear cada servicio asociado
                foreach ($req->servicios as $index => $servicioData) {
                    \Log::info("➡️ Creando servicio #{$index}", $servicioData);
                    
                    $serv = ServicioEmpresa::findOrFail($servicioData['id_servicio']);
                    $cantidadServicio = (int)$servicioData['cantidad'];
                    
                    // Determinar si usa tamaños
                    $usaTamanosCentro = \App\Models\ServicioCentro::where('id_centrotrabajo', $centroId)
                        ->where('id_servicio', $serv->id)
                        ->whereHas('tamanos')
                        ->exists();
                    
                    $precioUnitario = 0;
                    if (!$usaTamanosCentro) {
                        $precioUnitario = (float)$pricing->precioUnitario($centroId, $serv->id, null);
                    }
                    
                    // Crear SolicitudServicio
                    \App\Models\SolicitudServicio::create([
                        'solicitud_id'     => $sol->id,
                        'servicio_id'      => $serv->id,
                        'tipo_cobro'       => $usaTamanosCentro ? 'tamanos' : 'cantidad',
                        'cantidad'         => $cantidadServicio,
                        'precio_unitario'  => $precioUnitario,
                        'subtotal'         => $precioUnitario * $cantidadServicio,
                    ]);
                }
                
                // Recalcular totales de la solicitud
                $sol->recalcularTotales();
                
                // Manejar archivos adjuntos
                if ($req->hasFile('archivos')) {
                    $archivos = $req->file('archivos');
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
                
                DB::commit();
                
                // Notificar a coordinador
                try {
                    $serviciosNombres = $sol->servicios()->with('servicio')->get()->pluck('servicio.nombre')->join(', ');
                    Notifier::toRoleInCentro(
                        'coordinador',
                        $sol->id_centrotrabajo,
                        'Nueva solicitud',
                        "El cliente creó la solicitud {$sol->folio} con servicios: {$serviciosNombres} ({$sol->descripcion}).",
                        route('solicitudes.show',$sol->id)
                    );
                } catch (\Throwable $e) {
                    \Log::warning('Error al notificar solicitud', ['error' => $e->getMessage()]);
                }
                
                return redirect()
                    ->route('solicitudes.show', $sol->id)
                    ->with('ok', 'Solicitud con ' . count($req->servicios) . ' servicio(s) creada exitosamente');
                    
            } catch (\Throwable $ex) {
                DB::rollBack();
                throw $ex;
            }
        }

        // FLUJO NORMAL: Un solo servicio (compatibilidad con código anterior)
        // Usar un pequeño retry para evitar colisiones de folio bajo concurrencia
        $attempts = 0; $maxAttempts = 3; $lastException = null;
        while ($attempts < $maxAttempts) {
            try {
                // Determinar si el servicio usa tamaños EN ESTE centro (per-centro)
                $usaTamanosCentro = \App\Models\ServicioCentro::where('id_centrotrabajo', $centroId)
                    ->where('id_servicio', $serv->id)
                    ->whereHas('tamanos')
                    ->exists();

                if ($usaTamanosCentro) {
                    // NUEVO: flujo diferido. Solo capturamos TOTAL de piezas, sin desglose ni precios.
                    $req->validate([
                        'cantidad' => ['required','integer','min:1'],
                    ]);

                    $sol = Solicitud::create([
                        'folio'            => $this->generarFolio($centroId),
                        'id_cliente'       => $u->id,
                        'id_centrotrabajo' => $centroId,
                        'id_servicio'      => $serv->id,
                        'descripcion'      => $req->descripcion,
                        'id_centrocosto'   => (int)$req->id_centrocosto,
                        'id_marca'         => $req->filled('id_marca') ? (int)$req->id_marca : null,
                        'id_area'          => $areaId,
                        'cantidad'         => (int)$req->cantidad,
                        // Importes diferidos a la finalización de OT
                        'subtotal'         => 0,
                        'iva'              => 0,
                        'total'            => 0,
                        'tamanos_json'     => null,
                        'notas'            => $req->notas,
                        'estatus'          => 'pendiente',
                        'archivo_excel_stored_name' => $excelStoredName,
                        'archivo_excel_nombre_original' => $excelOriginalName,
                        'archivo_excel_subido_por' => $excelStoredName ? (int)$u->id : null,
                        'archivo_excel_subido_at' => $excelStoredName ? now() : null,
                    ]);
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
                        'id_area'          => $areaId,
                        'id_centrocosto'   => (int)$req->id_centrocosto,
                        'id_marca'         => $req->filled('id_marca') ? (int)$req->id_marca : null,
                        'cantidad'         => (int)$req->cantidad,
                        'subtotal'         => $subtotal,
                        'iva'              => $iva,
                        'total'            => $totalImporte,
                        'notas'            => $req->notas,
                        'estatus'          => 'pendiente',
                        'archivo_excel_stored_name' => $excelStoredName,
                        'archivo_excel_nombre_original' => $excelOriginalName,
                        'archivo_excel_subido_por' => $excelStoredName ? (int)$u->id : null,
                        'archivo_excel_subido_at' => $excelStoredName ? now() : null,
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
        $req->validate([
            'motivo' => ['required','string','min:3','max:2000']
        ]);

        $motivo = $req->input('motivo');

        $solicitud->update([
            'estatus' => 'rechazada',
            'aprobada_por' => Auth::id(),
            'aprobada_at' => now(),
            'motivo_rechazo' => $motivo,
        ]);
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
        $solicitud->load([
            'cliente',
            'servicio',
            'centro',
            'area',
            'centroCosto',
            'marca',
            'archivos',
            'tamanos',
            'ordenes',
            'servicios.servicio' // Eager load múltiples servicios
        ]);
        $user = $req->user();

        $canAprobar = $user->hasAnyRole(['coordinador','admin'])
            && $solicitud->estatus === 'pendiente';

        $canGenerarOt = $user->hasAnyRole(['coordinador','admin'])
            && $solicitud->estatus === 'aprobada';

        // Cotización para visualizar precios con IVA
        $cotizacion = $this->buildCotizacion($solicitud);

        return Inertia::render('Solicitudes/Show', [
            'solicitud' => $solicitud->toArray(),
            'can' => [
                'aprobar'  => $canAprobar,
                'rechazar' => $canAprobar,
                'generar_ot' => $canGenerarOt,
            ],
            'urls' => [
                'aprobar'    => route('solicitudes.aprobar', $solicitud),
                'rechazar'   => route('solicitudes.rechazar', $solicitud),
                'generar_ot' => route('ordenes.createFromSolicitud', $solicitud),
                'excel_origen' => $solicitud->archivo_excel_stored_name
                    ? route('solicitudes.excel.origen', $solicitud)
                    : null,
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

        // NUEVO: Si tiene múltiples servicios, construir cotización desde solicitud_servicios
        if ($solicitud->servicios()->exists()) {
            foreach ($solicitud->servicios as $solServicio) {
                $lines[] = [
                    'label'    => $solServicio->servicio->nombre ?? 'Servicio',
                    'tamano'   => null,
                    'cantidad' => $solServicio->cantidad,
                    'pu'       => $solServicio->precio_unitario,
                    'subtotal' => $solServicio->subtotal,
                ];
                $subtotal += $solServicio->subtotal;
            }
            $iva = $subtotal * $ivaRate;
            $total = $subtotal + $iva;
            return [
                'mode'      => 'multi_servicio',
                'lines'     => $lines,
                'subtotal'  => $subtotal,
                'iva'       => $iva,
                'total'     => $total,
            ];
        }

        // Flujo TRADICIONAL: Servicio único
        if (!$solicitud->id_servicio) {
            // Sin servicio asignado (edge case)
            return [
                'mode'      => 'sin_servicio',
                'lines'     => [],
                'subtotal'  => 0.0,
                'iva'       => 0.0,
                'total'     => 0.0,
            ];
        }

        // Determinar modo per-centro (ignora flag global en servicios_empresa)
        $usaTamanosCentro = \App\Models\ServicioCentro::where('id_centrotrabajo', $solicitud->id_centrotrabajo)
            ->where('id_servicio', $solicitud->id_servicio)
            ->whereHas('tamanos')
            ->exists();

        // Si hay desglose por tamaños, calcular por línea
        if ($usaTamanosCentro && $solicitud->relationLoaded('tamanos') && $solicitud->tamanos && $solicitud->tamanos->count() > 0) {
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
    } else if (!$usaTamanosCentro) {
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
        } else {
            // Servicio usa tamaños pero aún no hay desglose: precios diferidos
            $mode = 'pendiente_tamanos';
            $lines = [];
            $subtotal = 0.0;
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
     * Verifica si hay OTs validadas por Calidad sin autorizar que hayan excedido el tiempo límite.
     * Si encuentra alguna en el centro del usuario, bloquea la creación de nuevas solicitudes.
     * 
     * IMPORTANTE: El tiempo se cuenta desde que CALIDAD validó la OT, no desde que se completó.
     * Esto permite que el cliente tenga el tiempo completo para revisar después de la validación.
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

        // Buscar OTs que:
        // 1. Estén en estado 'completada' (aún no autorizadas por cliente)
        // 2. Tengan calidad_resultado = 'validado' (ya revisadas por calidad)
        // 3. No tengan autorización del cliente
        $otsValidadas = \App\Models\Orden::where('id_centrotrabajo', $centroId)
            ->where('estatus', 'completada')
            ->where('calidad_resultado', 'validado')
            ->whereDoesntHave('aprobaciones', function($q) {
                $q->where('tipo', 'cliente')->where('resultado', 'autorizado');
            })
            ->with('solicitud:id,folio')
            ->get(['id', 'id_solicitud']);

        if ($otsValidadas->isEmpty()) {
            return null; // No hay OTs validadas pendientes
        }

        // Filtrar solo las que hayan excedido el tiempo DESDE LA VALIDACIÓN DE CALIDAD
        // NOTE: aquí usamos tiempo "efectivo" que EXCLUYE sábados y domingos (se pausa el conteo)
        $otsVencidas = $otsValidadas->filter(function($ot) use ($timeoutMinutos) {
            // Buscar el registro de actividad cuando calidad validó
            $activityLog = \Spatie\Activitylog\Models\Activity::where('log_name', 'ordenes')
                ->where('subject_type', \App\Models\Orden::class)
                ->where('subject_id', $ot->id)
                ->where('event', 'calidad_validar')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$activityLog) {
                return false; // No se encontró registro de validación, no bloquear
            }

            $validadaEn = Carbon::parse($activityLog->created_at);
            $ahora = Carbon::now();

            // Calcular minutos efectivos excluyendo sábados y domingos
            $minutosTranscurridos = $this->businessMinutesBetween($validadaEn, $ahora);

            return $minutosTranscurridos >= $timeoutMinutos;
        });

        if ($otsVencidas->isEmpty()) {
            return null; // No hay bloqueo (las OTs validadas aún están dentro del tiempo)
        }

        // Construir mensaje de bloqueo
        $tiempoTexto = $timeoutMinutos >= 60 
            ? round($timeoutMinutos / 60) . ' hora(s)'
            : $timeoutMinutos . ' minuto(s)';

        $ordenesDetalle = $otsVencidas->map(function($ot) use ($timeoutMinutos) {
            // Obtener la fecha de validación de calidad
            $activityLog = \Spatie\Activitylog\Models\Activity::where('log_name', 'ordenes')
                ->where('subject_type', \App\Models\Orden::class)
                ->where('subject_id', $ot->id)
                ->where('event', 'calidad_validar')
                ->orderBy('created_at', 'desc')
                ->first();

            $validadaEn = $activityLog ? Carbon::parse($activityLog->created_at) : Carbon::parse($ot->updated_at);
            $transcurrido = $this->businessMinutesBetween($validadaEn, Carbon::now());
            $folio = $ot->solicitud ? $ot->solicitud->folio : 'OT-' . $ot->id;
            
            return [
                'id' => $ot->id,
                'folio' => $folio,
                'validada_hace' => $this->formatearTiempo($transcurrido),
                'url' => route('ordenes.show', $ot->id),
            ];
        })->toArray();

        $mensaje = sprintf(
            'No puedes crear nuevas solicitudes. Hay %d orden(es) de trabajo validada(s) por Calidad hace más de %s sin autorización del cliente. Por favor, autoriza las órdenes pendientes antes de continuar.',
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

    /**
     * Calcula la cantidad de minutos transcurridos entre dos instantes EXCLUYENDO
     * sábados y domingos. El conteo incluye cualquier hora del día mientras el día
     * sea lunes-viernes; si la ventana cruza sábado/domingo, esos minutos se omiten.
     *
     * @param \Carbon\Carbon|string $inicio
     * @param \Carbon\Carbon|string $fin
     * @return int minutos efectivos
     */
    public function businessMinutesBetween($inicio, $fin): int
    {
        $start = $inicio instanceof Carbon ? $inicio->copy() : Carbon::parse($inicio);
        $end = $fin instanceof Carbon ? $fin->copy() : Carbon::parse($fin);
        if ($end->lte($start)) return 0;

        $total = 0;

        $currentDay = $start->copy()->startOfDay();
        $lastDay = $end->copy()->startOfDay();

        while ($currentDay->lte($lastDay)) {
            if ($currentDay->isWeekend()) {
                $currentDay->addDay();
                continue;
            }

            $dayStartTs = max($start->getTimestamp(), $currentDay->getTimestamp());
            $dayEndTs = min($end->getTimestamp(), $currentDay->copy()->addDay()->getTimestamp());

            if ($dayEndTs > $dayStartTs) {
                $total += ($dayEndTs - $dayStartTs) / 60.0;
            }

            $currentDay->addDay();
        }

        return (int) round($total);
    }
}
