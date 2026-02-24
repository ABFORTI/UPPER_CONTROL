<?php

namespace App\Http\Controllers;

use App\Domain\Servicios\PricingService;
use App\Models\Area;
use App\Models\CentroCosto;
use App\Models\CentroTrabajo;
use App\Models\Cotizacion;
use App\Models\CotizacionAuditLog;
use App\Models\CotizacionItem;
use App\Models\CotizacionItemServicio;
use App\Models\Marca;
use App\Models\ServicioCentro;
use App\Models\ServicioEmpresa;
use App\Models\Solicitud;
use App\Models\User;
use App\Notifications\QuotationSentDatabaseNotification;
use App\Notifications\QuotationSentNotification;
use App\Services\Notifier;
use App\Services\QuotationApprovalService;
use App\Services\QuotationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class CotizacionController extends Controller
{
    private const IVA_RATE = 0.16;
    private const DEFAULT_EXPIRES_DAYS = 7;

    public function index(Request $req)
    {
        $this->authorize('viewAny', Cotizacion::class);

        /** @var User $u */
        $u = $req->user();

        $filters = [
            'estatus' => $req->string('estatus')->toString(),
            'client_id' => $req->input('client_id'),
            'date_from' => $req->string('date_from')->toString(),
            'date_to' => $req->string('date_to')->toString(),
            'q' => $req->string('q')->toString(),
        ];

        $q = Cotizacion::query()
            ->with([
                'cliente:id,name,email',
                'centro:id,nombre,prefijo',
                'centroCosto:id,nombre',
                'marca:id,nombre',
            ])
            ->when($filters['estatus'], fn($qq, $v) => $qq->where('estatus', $v))
            ->when($filters['client_id'], fn($qq, $v) => $qq->where('id_cliente', (int)$v))
            ->when($filters['date_from'], fn($qq, $v) => $qq->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'], fn($qq, $v) => $qq->whereDate('created_at', '<=', $v))
            ->when($filters['q'], function ($qq, $v) {
                $term = trim((string)$v);
                if ($term === '') return;
                $qq->where(function ($q2) use ($term) {
                    $q2->where('folio', 'like', '%' . $term . '%')
                        ->orWhere('notas', 'like', '%' . $term . '%')
                        ->orWhere('notes', 'like', '%' . $term . '%')
                        ->orWhereHas('items', function ($qi) use ($term) {
                            $qi->where('descripcion', 'like', '%' . $term . '%');
                        });
                });
            })
            ->orderByDesc('id');

        // Alcance según rol (alineado a policies existentes)
        if ($u->hasRole('admin')) {
            // todo
        } elseif ($u->hasRole('Cliente_Supervisor')) {
            $q->where('id_cliente', $u->id);
        } elseif ($u->hasRole('Cliente_Gerente')) {
            $q->where('id_centrotrabajo', $u->centro_trabajo_id);
        } elseif ($u->hasAnyRole(['gerente_upper', 'facturacion'])) {
            $ids = $this->allowedCentroIds($u);
            if (!empty($ids)) $q->whereIn('id_centrotrabajo', $ids);
        } else {
            // coordinador y otros: por centro principal
            $q->where('id_centrotrabajo', $u->centro_trabajo_id);
        }

        $paginator = $q->paginate(10)->withQueryString();

        $paginator->getCollection()->transform(function (Cotizacion $c) use ($u) {
            $raw = $c->getRawOriginal('created_at');
            $fecha = null;
            $fechaHumana = null;
            if ($raw) {
                try {
                    $dt = \Carbon\Carbon::parse($raw);
                    $fecha = $dt->format('Y-m-d H:i');
                    $fechaHumana = $dt->diffForHumans();
                } catch (\Throwable $e) {
                    $fecha = substr($raw, 0, 16);
                }
            }

            return [
                'id' => $c->id,
                'folio' => $c->folio,
                'estatus' => $c->estatus,
                'cliente' => ['id' => $c->cliente?->id, 'name' => $c->cliente?->name],
                'centro' => ['id' => $c->centro?->id, 'nombre' => $c->centro?->nombre],
                'centroCosto' => ['nombre' => $c->centroCosto?->nombre],
                'marca' => ['nombre' => $c->marca?->nombre],
                'subtotal' => (float)($c->subtotal ?? 0),
                'iva' => (float)($c->iva ?? 0),
                'total' => (float)($c->total ?? 0),
                'fecha' => $fecha,
                'fecha_humana' => $fechaHumana,
                'can' => [
                    'edit' => $u->can('update', $c) && $c->estatus === Cotizacion::ESTATUS_DRAFT,
                    'send' => $u->can('send', $c) && $c->estatus === Cotizacion::ESTATUS_DRAFT,
                    'duplicate' => $u->can('create', Cotizacion::class),
                ],
            ];
        });

        $clientesFilter = [];
        try {
            $clientesQ = User::role('Cliente_Supervisor')
                ->select('id', 'name', 'email', 'centro_trabajo_id')
                ->orderBy('name');
            if (!$u->hasRole('admin')) {
                $centroId = (int)($u->centro_trabajo_id ?? 0);
                if ($centroId) {
                    $clientesQ->where('centro_trabajo_id', $centroId);
                }
            }
            $clientesFilter = $clientesQ->limit(500)->get()->map(fn($x) => [
                'id' => (int)$x->id,
                'name' => (string)$x->name,
                'email' => (string)$x->email,
            ])->values()->all();
        } catch (\Throwable $e) {
            $clientesFilter = [];
        }

        return Inertia::render('Cotizaciones/Index', [
            'data' => $paginator,
            'filters' => [
                'estatus' => $filters['estatus'] ?: null,
                'client_id' => $filters['client_id'] ? (int)$filters['client_id'] : null,
                'date_from' => $filters['date_from'] ?: null,
                'date_to' => $filters['date_to'] ?: null,
                'q' => $filters['q'] ?: null,
            ],
            'clientesFilter' => $clientesFilter,
            'urls' => [
                'index' => route('cotizaciones.index'),
                'create' => route('cotizaciones.create'),
            ],
        ]);
    }

    public function edit(Request $req, Cotizacion $cotizacion)
    {
        $this->authorize('update', $cotizacion);

        if ($cotizacion->estatus !== Cotizacion::ESTATUS_DRAFT) {
            return redirect()->route('cotizaciones.show', $cotizacion->id)
                ->withErrors(['estatus' => 'Solo puedes editar cotizaciones en borrador.']);
        }

        /** @var User $u */
        $u = $req->user();

        $centroId = (int)$cotizacion->id_centrotrabajo;

        $clientes = User::role('Cliente_Supervisor')
            ->when(!$u->hasRole('admin') && $centroId, fn($qq) => $qq->where('centro_trabajo_id', $centroId))
            ->select('id', 'name', 'email', 'centro_trabajo_id')
            ->orderBy('name')
            ->get();

        $servicios = ServicioEmpresa::select('id', 'nombre', 'usa_tamanos')->orderBy('nombre')->get();

        $precios = [];
        if ($centroId) {
            $ids = $servicios->pluck('id')->all();
            $scs = ServicioCentro::with('tamanos')
                ->where('id_centrotrabajo', $centroId)
                ->whereIn('id_servicio', $ids)
                ->get();
            foreach ($scs as $sc) {
                $precios[$sc->id_servicio] = [
                    'precio_base' => (float)($sc->precio_base ?? 0),
                    'tamanos' => [
                        'chico' => optional($sc->tamanos->firstWhere('tamano', 'chico'))->precio,
                        'mediano' => optional($sc->tamanos->firstWhere('tamano', 'mediano'))->precio,
                        'grande' => optional($sc->tamanos->firstWhere('tamano', 'grande'))->precio,
                        'jumbo' => optional($sc->tamanos->firstWhere('tamano', 'jumbo'))->precio,
                    ],
                ];
            }
        }

        $hasCC = Schema::hasTable('centros_costos');
        $hasMarcas = Schema::hasTable('marcas');

        $cotizacion->load([
            'cliente:id,name,email',
            'centro:id,nombre,prefijo',
            'centroCosto:id,nombre',
            'marca:id,nombre',
            'area:id,nombre',
            'items.servicios.servicio:id,nombre,usa_tamanos',
        ]);

        $can = [
            'edit' => $u->can('update', $cotizacion) && $cotizacion->estatus === Cotizacion::ESTATUS_DRAFT,
            'send' => $u->can('send', $cotizacion) && $cotizacion->estatus === Cotizacion::ESTATUS_DRAFT,
            'duplicate' => $u->can('create', Cotizacion::class),
        ];

        return Inertia::render('Cotizaciones/Edit', [
            'cotizacion' => $cotizacion,
            'clientes' => $clientes,
            'servicios' => $servicios,
            'precios' => $precios,
            'iva' => self::IVA_RATE,
            'can' => $can,
            'urls' => [
                'update' => route('cotizaciones.update', $cotizacion->id),
                'send' => route('cotizaciones.send', $cotizacion->id),
                'recipients' => route('cotizaciones.recipients', $cotizacion->id),
                'show' => route('cotizaciones.show', $cotizacion->id),
                'duplicate' => route('cotizaciones.duplicate', $cotizacion->id),
                'index' => route('cotizaciones.index'),
            ],
            'areas' => ($centroId ? Area::where('id_centrotrabajo', $centroId)->activas()->orderBy('nombre')->get() : []),
            'centrosCostos' => ($hasCC && $centroId ? CentroCosto::where('id_centrotrabajo', $centroId)->activos()->orderBy('nombre')->get() : []),
            'marcas' => ($hasMarcas && $centroId ? Marca::where('id_centrotrabajo', $centroId)->activos()->orderBy('nombre')->get() : []),
        ]);
    }

    public function recipients(Request $req, Cotizacion $cotizacion)
    {
        // Mismo alcance/roles que el envío.
        $this->authorize('send', $cotizacion);

        $cotizacion->loadMissing(['cliente:id,name,email']);

        return response()->json([
            'data' => $this->getClientRecipientEmailsWithMeta($cotizacion->cliente),
        ]);
    }

    public function update(Request $req, Cotizacion $cotizacion)
    {
        $this->authorize('update', $cotizacion);

        if ($cotizacion->estatus !== Cotizacion::ESTATUS_DRAFT) {
            return back()->withErrors(['estatus' => 'Solo puedes editar cotizaciones en borrador.']);
        }

        /** @var User $u */
        $u = $req->user();
        $centroId = (int)$cotizacion->id_centrotrabajo;

        $req->validate([
            'id_cliente' => ['required', 'integer', 'exists:users,id'],
            'id_centrocosto' => ['required', 'integer', 'exists:centros_costos,id'],
            'id_marca' => ['nullable', 'integer', 'exists:marcas,id'],
            'id_area' => ['nullable', 'integer', 'exists:areas,id'],
            'notas' => ['nullable', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['required', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.notas' => ['nullable', 'string', 'max:5000'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.centro_costo_id' => ['nullable', 'integer'],
            'items.*.brand_id' => ['nullable', 'integer'],

            'items.*.servicios' => ['required', 'array', 'min:1'],
            'items.*.servicios.*.id_servicio' => ['required', 'integer', 'exists:servicios_empresa,id'],
            'items.*.servicios.*.cantidad' => ['nullable', 'integer', 'min:1'],
            'items.*.servicios.*.qty' => ['nullable', 'numeric', 'min:0.001'],
            'items.*.servicios.*.tamano' => ['nullable', 'string', 'max:20'],
            'items.*.servicios.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'items.*.servicios.*.notes' => ['nullable', 'string', 'max:5000'],
        ]);

        // Validar cliente/CC pertenezcan al centro
        $cliente = User::findOrFail((int)$req->id_cliente);
        if (!$cliente->hasRole('Cliente_Supervisor') && !$cliente->hasRole('Cliente_Gerente')) {
            return back()->withErrors(['id_cliente' => 'El usuario seleccionado no tiene rol de cliente.'])->withInput();
        }
        if (!$u->hasRole('admin') && (int)$cliente->centro_trabajo_id !== (int)$centroId) {
            return back()->withErrors(['id_cliente' => 'El cliente no pertenece al centro de la cotización.'])->withInput();
        }

        $cc = CentroCosto::find((int)$req->id_centrocosto);
        if (!$cc || (int)$cc->id_centrotrabajo !== (int)$centroId) {
            return back()->withErrors(['id_centrocosto' => 'El centro de costos no pertenece al centro de la cotización.'])->withInput();
        }

        if ($req->filled('id_marca')) {
            $marca = Marca::find((int)$req->id_marca);
            if (!$marca || (int)$marca->id_centrotrabajo !== (int)$centroId) {
                return back()->withErrors(['id_marca' => 'La marca seleccionada no pertenece al centro.'])->withInput();
            }
        }
        if ($req->filled('id_area')) {
            $area = Area::find((int)$req->id_area);
            if (!$area || (int)$area->id_centrotrabajo !== (int)$centroId) {
                return back()->withErrors(['id_area' => 'El área seleccionada no pertenece al centro.'])->withInput();
            }
        }

        $pricing = app(PricingService::class);

        DB::transaction(function () use ($req, $cotizacion, $centroId, $pricing) {
            $cotizacion->update([
                'id_cliente' => (int)$req->id_cliente,
                'id_centrocosto' => (int)$req->id_centrocosto,
                'id_marca' => $req->filled('id_marca') ? (int)$req->id_marca : null,
                'id_area' => $req->filled('id_area') ? (int)$req->id_area : null,
                'notas' => $req->input('notas'),
                'expires_at' => $req->filled('expires_at') ? \Carbon\Carbon::parse($req->input('expires_at')) : null,
            ]);

            // Re-escribir ítems/servicios (simple y consistente)
            $oldItemIds = $cotizacion->items()->pluck('id')->all();
            if (!empty($oldItemIds)) {
                CotizacionItemServicio::whereIn('cotizacion_item_id', $oldItemIds)->delete();
            }
            CotizacionItem::where('cotizacion_id', (int)$cotizacion->id)->delete();

            $subtotalAll = 0.0;
            $ivaAll = 0.0;
            $totalAll = 0.0;

            $items = (array)$req->input('items', []);
            foreach ($items as $itemData) {
                $item = CotizacionItem::create([
                    'cotizacion_id' => $cotizacion->id,
                    'descripcion' => $itemData['descripcion'],
                    'cantidad' => (int)$itemData['cantidad'],
                    'notas' => $itemData['notas'] ?? null,
                    'unit' => $itemData['unit'] ?? null,
                    'centro_costo_id' => $itemData['centro_costo_id'] ?? null,
                    'brand_id' => $itemData['brand_id'] ?? null,
                ]);

                $servicios = (array)($itemData['servicios'] ?? []);
                foreach ($servicios as $svcData) {
                    $servicioId = (int)$svcData['id_servicio'];
                    $tamano = isset($svcData['tamano']) && $svcData['tamano'] !== '' ? (string)$svcData['tamano'] : null;

                    $cantidadInt = (int)($svcData['cantidad'] ?? $item->cantidad);
                    if ($cantidadInt <= 0) $cantidadInt = (int)$item->cantidad;
                    if ($cantidadInt <= 0) $cantidadInt = 1;

                    $qtyDecimal = isset($svcData['qty']) && $svcData['qty'] !== '' ? (float)$svcData['qty'] : null;

                    // Validar servicio con precio configurado en este centro
                    $sc = ServicioCentro::where('id_centrotrabajo', $centroId)
                        ->where('id_servicio', $servicioId)
                        ->first();
                    if (!$sc) {
                        throw new \RuntimeException('El servicio seleccionado no tiene precio configurado para este centro.');
                    }

                    $pu = isset($svcData['precio_unitario']) && $svcData['precio_unitario'] !== ''
                        ? (float)$svcData['precio_unitario']
                        : (float)$pricing->precioUnitario($centroId, $servicioId, $tamano);

                    $qtyForCalc = $qtyDecimal !== null ? $qtyDecimal : (float)$cantidadInt;
                    if ($qtyForCalc <= 0) $qtyForCalc = (float)$cantidadInt;

                    $sub = $pu * $qtyForCalc;
                    $iva = $sub * self::IVA_RATE;
                    $tot = $sub + $iva;

                    CotizacionItemServicio::create([
                        'cotizacion_item_id' => $item->id,
                        'id_servicio' => $servicioId,
                        'tamano' => $tamano,
                        'tamanos_json' => null,
                        'cantidad' => $cantidadInt,
                        'qty' => $qtyDecimal,
                        'precio_unitario' => $pu,
                        'subtotal' => $sub,
                        'iva' => $iva,
                        'total' => $tot,
                        'notes' => $svcData['notes'] ?? null,
                    ]);

                    $subtotalAll += $sub;
                    $ivaAll += $iva;
                    $totalAll += $tot;
                }
            }

            $cotizacion->update([
                'subtotal' => $subtotalAll,
                'iva' => $ivaAll,
                'total' => $totalAll,
            ]);
        });

        $this->audit($req, $cotizacion, 'updated', [
            'items' => count((array)$req->input('items', [])),
        ]);

        return redirect()->route('cotizaciones.edit', $cotizacion->id)->with('ok', 'Cotización guardada');
    }

    public function duplicate(Request $req, Cotizacion $cotizacion)
    {
        $this->authorize('create', Cotizacion::class);

        /** @var User $u */
        $u = $req->user();
        $centroId = (int)$cotizacion->id_centrotrabajo;

        $cotizacion->loadMissing(['items.servicios']);

        $new = DB::transaction(function () use ($cotizacion, $u, $centroId) {
            $c = Cotizacion::create([
                'folio' => $this->generarFolio($centroId),
                'created_by' => (int)$u->id,
                'id_cliente' => (int)$cotizacion->id_cliente,
                'id_centrotrabajo' => (int)$cotizacion->id_centrotrabajo,
                'id_centrocosto' => (int)$cotizacion->id_centrocosto,
                'id_marca' => $cotizacion->id_marca ? (int)$cotizacion->id_marca : null,
                'id_area' => $cotizacion->id_area ? (int)$cotizacion->id_area : null,
                'notas' => $cotizacion->notas,
                'estatus' => Cotizacion::ESTATUS_DRAFT,
                'subtotal' => 0,
                'iva' => 0,
                'total' => 0,
                'expires_at' => null,
                'sent_at' => null,
                'approved_at' => null,
                'rejected_at' => null,
                'cancelled_at' => null,
                'motivo_rechazo' => null,
                'approval_token_hash' => null,
            ]);

            $subtotalAll = 0.0;
            $ivaAll = 0.0;
            $totalAll = 0.0;

            foreach ($cotizacion->items as $item) {
                $newItem = CotizacionItem::create([
                    'cotizacion_id' => $c->id,
                    'descripcion' => $item->descripcion,
                    'cantidad' => (int)($item->cantidad ?? 1),
                    'notas' => $item->notas,
                    'unit' => $item->unit ?? null,
                    'centro_costo_id' => $item->centro_costo_id ?? null,
                    'brand_id' => $item->brand_id ?? null,
                    'product_name' => $item->product_name ?? null,
                    'quantity' => $item->quantity ?? null,
                    'metadata' => $item->metadata ?? null,
                ]);

                foreach ($item->servicios as $line) {
                    $sub = (float)($line->subtotal ?? 0);
                    $iva = (float)($line->iva ?? 0);
                    $tot = (float)($line->total ?? 0);

                    CotizacionItemServicio::create([
                        'cotizacion_item_id' => $newItem->id,
                        'id_servicio' => (int)$line->id_servicio,
                        'tamano' => $line->tamano,
                        'tamanos_json' => $line->tamanos_json,
                        'cantidad' => (int)($line->cantidad ?? 1),
                        'qty' => $line->qty,
                        'precio_unitario' => (float)($line->precio_unitario ?? 0),
                        'subtotal' => $sub,
                        'iva' => $iva,
                        'total' => $tot,
                        'notes' => $line->notes,
                    ]);

                    $subtotalAll += $sub;
                    $ivaAll += $iva;
                    $totalAll += $tot;
                }
            }

            $c->update([
                'subtotal' => $subtotalAll,
                'iva' => $ivaAll,
                'total' => $totalAll,
            ]);

            return $c;
        });

        $this->audit($req, $new, 'duplicated', [
            'from' => (int)$cotizacion->id,
        ]);

        return redirect()->route('cotizaciones.edit', $new->id)->with('ok', 'Cotización duplicada');
    }

    public function create(Request $req)
    {
        $this->authorize('create', Cotizacion::class);

        /** @var User $u */
        $u = Auth::user();

        // Centro fijo para coordinador; admin puede elegir
        $canChooseCentro = $u && $u->hasRole('admin');
        $selectedCentroId = (int)($u->centro_trabajo_id ?? 0) ?: null;

        $centros = collect();
        if ($canChooseCentro) {
            $centros = CentroTrabajo::select('id', 'nombre', 'prefijo')->orderBy('nombre')->get();
            $requestedCentroId = (int)($req->input('centro') ?? 0) ?: null;
            if ($requestedCentroId && $centros->firstWhere('id', $requestedCentroId)) {
                $selectedCentroId = $requestedCentroId;
            }
            $selectedCentroId = $selectedCentroId ?: (int)($centros->first()?->id ?? 0) ?: null;
        }

        // Listas por centro (en create cargamos solo el centro seleccionado por simplicidad)
        $centroId = (int)($selectedCentroId ?? 0);

        $clientes = User::role('Cliente_Supervisor')
            ->when(!$canChooseCentro && $centroId, fn($qq) => $qq->where('centro_trabajo_id', $centroId))
            ->select('id', 'name', 'email', 'centro_trabajo_id')
            ->orderBy('name')
            ->get();

        $servicios = ServicioEmpresa::select('id', 'nombre', 'usa_tamanos')
            ->orderBy('nombre')->get();

        // Precios por centro (igual que Solicitudes/Create)
        $precios = [];
        if ($centroId) {
            $ids = $servicios->pluck('id')->all();
            $scs = ServicioCentro::with('tamanos')
                ->where('id_centrotrabajo', $centroId)
                ->whereIn('id_servicio', $ids)
                ->get();
            foreach ($scs as $sc) {
                $precios[$sc->id_servicio] = [
                    'precio_base' => (float)($sc->precio_base ?? 0),
                    'tamanos' => [
                        'chico' => optional($sc->tamanos->firstWhere('tamano', 'chico'))->precio,
                        'mediano' => optional($sc->tamanos->firstWhere('tamano', 'mediano'))->precio,
                        'grande' => optional($sc->tamanos->firstWhere('tamano', 'grande'))->precio,
                        'jumbo' => optional($sc->tamanos->firstWhere('tamano', 'jumbo'))->precio,
                    ],
                ];
            }
        }

        $hasCC = Schema::hasTable('centros_costos');
        $hasMarcas = Schema::hasTable('marcas');

        return Inertia::render('Cotizaciones/Create', [
            'clientes' => $clientes,
            'servicios' => $servicios,
            'precios' => $precios,
            'centros' => $centros,
            'canChooseCentro' => $canChooseCentro,
            'selectedCentroId' => $selectedCentroId,
            'iva' => self::IVA_RATE,
            'urls' => [
                'store' => route('cotizaciones.store'),
            ],
            'areas' => ($centroId ? Area::where('id_centrotrabajo', $centroId)->activas()->orderBy('nombre')->get() : []),
            'centrosCostos' => ($hasCC && $centroId ? CentroCosto::where('id_centrotrabajo', $centroId)->activos()->orderBy('nombre')->get() : []),
            'marcas' => ($hasMarcas && $centroId ? Marca::where('id_centrotrabajo', $centroId)->activos()->orderBy('nombre')->get() : []),
        ]);
    }

    public function store(Request $req)
    {
        $this->authorize('create', Cotizacion::class);

        /** @var User $u */
        $u = $req->user();

        $canChooseCentro = $u->hasRole('admin');
        $centroId = $canChooseCentro
            ? (int)$req->input('id_centrotrabajo')
            : (int)($u->centro_trabajo_id ?? 0);

        $req->validate([
            'id_centrotrabajo' => $canChooseCentro ? ['required', 'integer', 'exists:centros_trabajo,id'] : ['nullable'],
            'id_cliente' => ['required', 'integer', 'exists:users,id'],
            'id_centrocosto' => ['required', 'integer', 'exists:centros_costos,id'],
            'id_marca' => ['nullable', 'integer', 'exists:marcas,id'],
            'id_area' => ['nullable', 'integer', 'exists:areas,id'],
            'notas' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['required', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.notas' => ['nullable', 'string', 'max:5000'],
            'items.*.servicios' => ['required', 'array', 'min:1'],
            'items.*.servicios.*.id_servicio' => ['required', 'integer', 'exists:servicios_empresa,id'],
            'items.*.servicios.*.cantidad' => ['nullable', 'integer', 'min:1'],
            'items.*.servicios.*.tamano' => ['nullable', 'string', 'max:20'],
        ]);

        if (!$centroId) {
            return back()->withErrors(['centro' => 'No se pudo determinar el centro de trabajo.'])->withInput();
        }

        // Validar que el cliente pertenezca al centro (misma lógica de facturas)
        $cliente = User::findOrFail((int)$req->id_cliente);
        if (!$cliente->hasRole('Cliente_Supervisor') && !$cliente->hasRole('Cliente_Gerente')) {
            return back()->withErrors(['id_cliente' => 'El usuario seleccionado no tiene rol de cliente.'])->withInput();
        }
        if (!$canChooseCentro && (int)$cliente->centro_trabajo_id !== (int)$centroId) {
            return back()->withErrors(['id_cliente' => 'El cliente no pertenece a tu centro de trabajo.'])->withInput();
        }

        $cc = CentroCosto::find((int)$req->id_centrocosto);
        if (!$cc || (int)$cc->id_centrotrabajo !== (int)$centroId) {
            return back()->withErrors(['id_centrocosto' => 'El centro de costos no pertenece al centro seleccionado.'])->withInput();
        }

        if ($req->filled('id_marca')) {
            $marca = Marca::find((int)$req->id_marca);
            if (!$marca || (int)$marca->id_centrotrabajo !== (int)$centroId) {
                return back()->withErrors(['id_marca' => 'La marca seleccionada no pertenece al centro seleccionado.'])->withInput();
            }
        }

        if ($req->filled('id_area')) {
            $area = Area::find((int)$req->id_area);
            if (!$area || (int)$area->id_centrotrabajo !== (int)$centroId) {
                return back()->withErrors(['id_area' => 'El área seleccionada no pertenece al centro seleccionado.'])->withInput();
            }
        }

        $pricing = app(PricingService::class);

        $cotizacion = DB::transaction(function () use ($req, $u, $centroId, $pricing) {
            // Generar folio con retry (similar a SolicitudController)
            $attempts = 0;
            $maxAttempts = 3;
            $lastException = null;

            while ($attempts < $maxAttempts) {
                try {
                    /** @var Cotizacion $c */
                    $c = Cotizacion::create([
                        'folio' => $this->generarFolio($centroId),
                        'created_by' => $u->id,
                        'id_cliente' => (int)$req->id_cliente,
                        'id_centrotrabajo' => $centroId,
                        'id_centrocosto' => (int)$req->id_centrocosto,
                        'id_marca' => $req->filled('id_marca') ? (int)$req->id_marca : null,
                        'id_area' => $req->filled('id_area') ? (int)$req->id_area : null,
                        'notas' => $req->input('notas'),
                        'estatus' => Cotizacion::ESTATUS_DRAFT,
                        'subtotal' => 0,
                        'iva' => 0,
                        'total' => 0,
                    ]);

                    $subtotalAll = 0.0;
                    $ivaAll = 0.0;
                    $totalAll = 0.0;

                    $items = (array)$req->input('items', []);
                    foreach ($items as $itemData) {
                        $item = CotizacionItem::create([
                            'cotizacion_id' => $c->id,
                            'descripcion' => $itemData['descripcion'],
                            'cantidad' => (int)$itemData['cantidad'],
                            'notas' => $itemData['notas'] ?? null,
                        ]);

                        $servicios = (array)($itemData['servicios'] ?? []);
                        foreach ($servicios as $svcData) {
                            $servicioId = (int)$svcData['id_servicio'];
                            $cantidad = (int)($svcData['cantidad'] ?? $item->cantidad);
                            $tamano = isset($svcData['tamano']) && $svcData['tamano'] !== '' ? (string)$svcData['tamano'] : null;

                            // Asegurar que el servicio tenga precio configurado en este centro
                            $sc = ServicioCentro::where('id_centrotrabajo', $centroId)
                                ->where('id_servicio', $servicioId)
                                ->first();
                            if (!$sc) {
                                throw new \RuntimeException('El servicio seleccionado no tiene precio configurado para este centro.');
                            }

                            $pu = (float)$pricing->precioUnitario($centroId, $servicioId, $tamano);
                            $sub = $pu * $cantidad;
                            $iva = $sub * self::IVA_RATE;
                            $tot = $sub + $iva;

                            CotizacionItemServicio::create([
                                'cotizacion_item_id' => $item->id,
                                'id_servicio' => $servicioId,
                                'tamano' => $tamano,
                                'tamanos_json' => null,
                                'cantidad' => $cantidad,
                                'precio_unitario' => $pu,
                                'subtotal' => $sub,
                                'iva' => $iva,
                                'total' => $tot,
                            ]);

                            $subtotalAll += $sub;
                            $ivaAll += $iva;
                            $totalAll += $tot;
                        }
                    }

                    $c->update([
                        'subtotal' => $subtotalAll,
                        'iva' => $ivaAll,
                        'total' => $totalAll,
                    ]);

                    return $c;
                } catch (\Throwable $ex) {
                    $isUnique = $ex instanceof \Illuminate\Database\UniqueConstraintViolationException
                        || ($ex instanceof \Illuminate\Database\QueryException && (string)$ex->getCode() === '23000');
                    if ($isUnique) {
                        $lastException = $ex;
                        $attempts++;
                        usleep(120000);
                        continue;
                    }
                    throw $ex;
                }
            }

            throw $lastException ?? new \RuntimeException('No fue posible generar un folio único');
        });

        $this->audit($req, $cotizacion, 'created', [
            'items' => count((array)$req->input('items', [])),
            'id_cliente' => (int)$cotizacion->id_cliente,
        ]);

        return redirect()->route('cotizaciones.show', $cotizacion->id)->with('ok', 'Cotización creada');
    }

    public function show(Request $req, Cotizacion $cotizacion)
    {
        $this->authorize('view', $cotizacion);

        $cotizacion->load([
            'creador:id,name,email',
            'cliente:id,name,email',
            'centro:id,nombre,prefijo',
            'centroCosto:id,nombre',
            'marca:id,nombre',
            'area:id,nombre',
            'items.servicios.servicio:id,nombre,usa_tamanos',
            'auditLogs.actorUser:id,name',
            'auditLogs.actorClient:id,name',
        ]);

        /** @var User $u */
        $u = $req->user();

        // Estados derivados
        if ($cotizacion->isExpired() && $cotizacion->estatus === Cotizacion::ESTATUS_SENT) {
            // No persistimos aquí (solo vista) para evitar side-effects en GET.
        }

        $canSend = $u->can('send', $cotizacion) && $cotizacion->estatus === Cotizacion::ESTATUS_DRAFT;
        $canCancel = $u->can('cancel', $cotizacion) && in_array($cotizacion->estatus, [Cotizacion::ESTATUS_DRAFT, Cotizacion::ESTATUS_SENT], true);
        $canApprove = $u->can('approve', $cotizacion) && $cotizacion->estatus === Cotizacion::ESTATUS_SENT && !$cotizacion->isExpired();
        $canReject = $u->can('reject', $cotizacion) && $cotizacion->estatus === Cotizacion::ESTATUS_SENT && !$cotizacion->isExpired();

        $auditLogs = $cotizacion->auditLogs
            ->sortByDesc('id')
            ->values()
            ->map(function ($log) {
                $createdAt = null;
                try {
                    $createdAt = $log->created_at?->toISOString();
                } catch (\Throwable $e) {
                    $createdAt = (string)($log->created_at ?? null);
                }

                $actor = null;
                if ($log->actorClient) {
                    $actor = ['type' => 'client', 'id' => $log->actorClient->id, 'name' => $log->actorClient->name];
                } elseif ($log->actorUser) {
                    $actor = ['type' => 'user', 'id' => $log->actorUser->id, 'name' => $log->actorUser->name];
                }

                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'actor' => $actor,
                    'payload' => $log->payload,
                    'created_at' => $createdAt,
                ];
            });

        return Inertia::render('Cotizaciones/Show', [
            'cotizacion' => $cotizacion,
            'auditLogs' => $auditLogs,
            'can' => [
                'send' => $canSend,
                'cancel' => $canCancel,
                'approve' => $canApprove,
                'reject' => $canReject,
            ],
            'urls' => [
                'send' => route('cotizaciones.send', $cotizacion->id),
                'recipients' => route('cotizaciones.recipients', $cotizacion->id),
                'cancel' => route('cotizaciones.cancel', $cotizacion->id),
                'approve' => route('cotizaciones.approve', $cotizacion->id),
                'reject' => route('cotizaciones.reject', $cotizacion->id),
                'pdf' => route('cotizaciones.pdf', $cotizacion->id),
            ],
        ]);
    }

    public function pdf(Request $req, Cotizacion $cotizacion)
    {
        $this->authorize('view', $cotizacion);

        $cotizacion->load([
            'creador:id,name,email',
            'cliente:id,name,email',
            'centro:id,nombre,prefijo',
            'centroCosto:id,nombre',
            'marca:id,nombre',
            'area:id,nombre',
            'items.servicios.servicio:id,nombre,usa_tamanos',
        ]);

        $folio = $cotizacion->folio ?: ('COT-' . $cotizacion->id);
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', 'COT_' . $folio . '.pdf');

        return PDF::loadView('pdf.cotizacion', [
            'cotizacion' => $cotizacion,
        ])
            ->setPaper('letter')
            ->download($filename);
    }

    public function review(Request $req, Cotizacion $cotizacion)
    {
        // Ruta firmada + auth; adicionalmente valida permisos por policy
        $this->authorize('view', $cotizacion);

        // Para campanita / navegación interna, el token NO es obligatorio.
        $this->enforceClientApprovalToken($req, $cotizacion, false);

        $cotizacion->load([
            'creador:id,name,email',
            'cliente:id,name,email',
            'centro:id,nombre,prefijo',
            'centroCosto:id,nombre',
            'marca:id,nombre',
            'area:id,nombre',
            'items.servicios.servicio:id,nombre,usa_tamanos',
        ]);

        /** @var User $u */
        $u = $req->user();

        $token = $req->string('token')->toString();
        $isClient = $u->hasAnyRole(['Cliente_Supervisor', 'Cliente_Gerente']);
        $tokenRequiredForActions = $isClient && (bool)$cotizacion->approval_token_hash;

        $canApprove = $u->can('approve', $cotizacion) && $cotizacion->estatus === Cotizacion::ESTATUS_SENT && !$cotizacion->isExpired();
        $canReject = $u->can('reject', $cotizacion) && $cotizacion->estatus === Cotizacion::ESTATUS_SENT && !$cotizacion->isExpired();

        $isExpired = $cotizacion->isExpired() && $cotizacion->estatus === Cotizacion::ESTATUS_SENT;

        // Si es cliente y el sistema ya usa token, no permitir acciones sin token.
        if ($tokenRequiredForActions && $token === '') {
            $canApprove = false;
            $canReject = false;
        }

        return Inertia::render('Cotizaciones/Review', [
            'cotizacion' => $cotizacion,
            'token' => $req->string('token')->toString() ?: null,
            'token_required' => $tokenRequiredForActions,
            'is_expired' => $isExpired,
            'expired_message' => $isExpired ? 'Cotización expirada' : null,
            'can' => [
                'approve' => $canApprove,
                'reject' => $canReject,
            ],
            'urls' => [
                'approve' => route('cotizaciones.approve', $cotizacion->id),
                'reject' => route('cotizaciones.reject', $cotizacion->id),
                'pdf' => route('cotizaciones.pdf', $cotizacion->id),
            ],
        ]);
    }

    public function send(Request $req, Cotizacion $cotizacion)
    {
        $this->authorize('send', $cotizacion);

        if ($cotizacion->estatus !== Cotizacion::ESTATUS_DRAFT) {
            return back()->withErrors(['estatus' => 'Solo puedes enviar cotizaciones en borrador.']);
        }

        $cotizacion->loadMissing(['cliente:id,name,email']);

        $data = $req->validate([
            'expires_at' => ['nullable', 'date'],
            'recipient_email' => ['nullable', 'string', 'email:rfc'],
        ]);

        $recipientEmail = isset($data['recipient_email']) ? trim((string)$data['recipient_email']) : '';
        $recipientEmail = $recipientEmail !== '' ? strtolower($recipientEmail) : '';

        if ($recipientEmail !== '') {
            $allowed = array_map('strtolower', $this->getClientRecipientEmails($cotizacion->cliente));
            if (!in_array($recipientEmail, $allowed, true)) {
                return back()->withErrors(['recipient_email' => 'Selecciona un correo válido del cliente.'])->withInput();
            }
        }

        $expiresAt = $req->filled('expires_at')
            ? \Carbon\Carbon::parse($req->input('expires_at'))
            : ($cotizacion->expires_at ?: now()->addDays(self::DEFAULT_EXPIRES_DAYS));

        $cotizacion->update([
            'estatus' => Cotizacion::ESTATUS_SENT,
            'sent_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        $token = app(QuotationService::class)->generateApprovalToken($cotizacion);

        $this->audit($req, $cotizacion, 'sent', [
            'sent_at' => optional($cotizacion->sent_at)->toISOString(),
            'expires_at' => optional($cotizacion->expires_at)->toISOString(),
            'recipient_email' => $recipientEmail !== '' ? $recipientEmail : null,
        ]);

        $url = route('client.public.quotations.show', [
            'cotizacion' => $cotizacion->id,
            'token' => $token,
        ]);

        try {
            $itemsCount = (int)($cotizacion->items()->count());
            $expiresAtIso = optional($expiresAt)->toISOString();

            if ($recipientEmail !== '') {
                // Campanita (DB) para el cliente (sin token)
                $cotizacion->cliente?->notify(new QuotationSentDatabaseNotification(
                    $cotizacion,
                    $itemsCount,
                    $expiresAtIso,
                ));

                // Correo (con token) al destinatario seleccionado
                Notification::route('mail', $recipientEmail)->notify(new QuotationSentNotification(
                    $cotizacion,
                    $token,
                    $itemsCount,
                    $expiresAtIso,
                ));
            } else {
                // Por defecto: DB + mail al correo resuelto por User::routeNotificationForMail (incluye contacto primario)
                $cotizacion->cliente?->notify(new QuotationSentNotification(
                    $cotizacion,
                    $token,
                    $itemsCount,
                    $expiresAtIso,
                ));
            }
        } catch (\Throwable $e) {
            // noop
        }

        return back()->with('ok', 'Cotización enviada al cliente');
    }

    private function getClientRecipientEmails(?User $client): array
    {
        if (!$client) return [];

        $emails = [];

        $base = is_string($client->email) ? trim($client->email) : '';
        if ($base !== '') $emails[] = strtolower($base);

        try {
            if (Schema::hasTable('client_contacts')) {
                $rows = DB::table('client_contacts')
                    ->where('client_id', (int)$client->id)
                    ->whereNotNull('email')
                    ->where('email', '<>', '')
                    ->orderByDesc('is_primary')
                    ->orderBy('id')
                    ->pluck('email')
                    ->all();

                foreach ($rows as $r) {
                    $r = is_string($r) ? trim($r) : '';
                    if ($r !== '') $emails[] = strtolower($r);
                }
            }
        } catch (\Throwable $e) {
            // noop
        }

        return array_values(array_unique($emails));
    }

    private function getClientRecipientEmailsWithMeta(?User $client): array
    {
        if (!$client) return [];

        $out = [];
        $seen = [];

        $push = function (string $email, string $label, bool $isPrimary = false) use (&$out, &$seen) {
            $e = strtolower(trim($email));
            if ($e === '' || isset($seen[$e])) return;
            $seen[$e] = true;
            $out[] = ['email' => $e, 'label' => $label, 'is_primary' => $isPrimary];
        };

        $base = is_string($client->email) ? trim($client->email) : '';
        if ($base !== '') {
            $push($base, $base, false);
        }

        try {
            if (Schema::hasTable('client_contacts')) {
                $rows = DB::table('client_contacts')
                    ->where('client_id', (int)$client->id)
                    ->whereNotNull('email')
                    ->where('email', '<>', '')
                    ->orderByDesc('is_primary')
                    ->orderBy('id')
                    ->get(['email', 'name', 'is_primary']);

                foreach ($rows as $row) {
                    $email = is_string($row->email ?? null) ? trim($row->email) : '';
                    if ($email === '') continue;
                    $name = is_string($row->name ?? null) ? trim($row->name) : '';
                    $isPrimary = (bool)($row->is_primary ?? false);
                    $label = $name !== '' ? ($name . ' — ' . $email) : $email;
                    if ($isPrimary) $label .= ' (principal)';
                    $push($email, $label, $isPrimary);
                }
            }
        } catch (\Throwable $e) {
            // noop
        }

        if (!$out && $base !== '') {
            $push($base, $base, true);
        }

        if ($out && !collect($out)->contains(fn($x) => !empty($x['is_primary']))) {
            $out[0]['is_primary'] = true;
        }

        return $out;
    }

    public function cancel(Request $req, Cotizacion $cotizacion)
    {
        $this->authorize('cancel', $cotizacion);

        if (!in_array($cotizacion->estatus, [Cotizacion::ESTATUS_DRAFT, Cotizacion::ESTATUS_SENT], true)) {
            return back()->withErrors(['estatus' => 'No se puede cancelar en el estatus actual.']);
        }

        $estabaEnviada = $cotizacion->estatus === Cotizacion::ESTATUS_SENT;

        $cotizacion->update([
            'estatus' => Cotizacion::ESTATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $this->audit($req, $cotizacion, 'cancelled');

        // Notificar al cliente si la cotización ya había sido enviada
        if ($estabaEnviada && $cotizacion->id_cliente) {
            Notifier::toUser(
                $cotizacion->id_cliente,
                'Cotización Cancelada',
                "La cotización #{$cotizacion->id} que recibiste ha sido cancelada.",
                route('cotizaciones.show', $cotizacion->id)
            );
        }

        return back()->with('ok', 'Cotización cancelada');
    }

    public function approve(Request $req, Cotizacion $cotizacion)
    {
        $this->authorize('approve', $cotizacion);

        // Para clientes, el token debe estar presente y ser válido.
        $this->enforceClientApprovalToken($req, $cotizacion, true);

        if ($cotizacion->estatus !== Cotizacion::ESTATUS_SENT) {
            return back()->withErrors(['estatus' => 'Solo puedes autorizar cotizaciones enviadas.']);
        }

        if ($cotizacion->isExpired()) {
            $cotizacion->update(['estatus' => Cotizacion::ESTATUS_EXPIRED]);
            return back()->withErrors(['estatus' => 'Esta cotización ya expiró.']);
        }

        $u = $req->user();
        $actorClientId = null;
        if ($u && $u->hasAnyRole(['Cliente_Supervisor', 'Cliente_Gerente'])) {
            $actorClientId = (int)$u->id;
        }

        try {
            app(QuotationApprovalService::class)->approveSentQuotation(
                $cotizacion,
                $actorClientId,
                'web',
                [
                    'ip' => $req->ip(),
                    'user_agent' => (string)$req->userAgent(),
                ]
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['estatus' => $e->getMessage()]);
        }

        $solicitudesCount = 0;
        if (Schema::hasTable('solicitudes')) {
            $solicitudesCount = Solicitud::where('id_cotizacion', (int)$cotizacion->id)->count();
        }

        return redirect()->route('cotizaciones.show', $cotizacion->id)
            ->with('ok', 'Cotización autorizada. Se generaron ' . (int)$solicitudesCount . ' solicitud(es).');
    }

    public function reject(Request $req, Cotizacion $cotizacion)
    {
        $this->authorize('reject', $cotizacion);

        // Para clientes, el token debe estar presente y ser válido.
        $this->enforceClientApprovalToken($req, $cotizacion, true);

        if ($cotizacion->estatus !== Cotizacion::ESTATUS_SENT) {
            return back()->withErrors(['estatus' => 'Solo puedes rechazar cotizaciones enviadas.']);
        }

        if ($cotizacion->isExpired()) {
            $cotizacion->update(['estatus' => Cotizacion::ESTATUS_EXPIRED]);
            return back()->withErrors(['estatus' => 'Esta cotización ya expiró.']);
        }

        $req->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $cotizacion->update([
            'estatus' => Cotizacion::ESTATUS_REJECTED,
            'rejected_at' => now(),
            'motivo_rechazo' => $req->input('motivo'),
        ]);

        $this->audit($req, $cotizacion, 'rejected', [
            'motivo' => (string)$req->input('motivo'),
        ]);

        try {
            Notifier::toUser(
                (int)$cotizacion->created_by,
                'Cotización rechazada',
                "El cliente rechazó la cotización {$cotizacion->folio}.",
                route('cotizaciones.show', $cotizacion->id)
            );
        } catch (\Throwable $e) { /* noop */ }

        return redirect()->route('cotizaciones.show', $cotizacion->id)->with('ok', 'Cotización rechazada');
    }

    private function audit(Request $req, Cotizacion $cotizacion, string $action, array $payload = []): void
    {
        try {
            $u = $req->user();

            $actorUserId = null;
            $actorClientId = null;
            if ($u) {
                if ($u->hasAnyRole(['Cliente_Supervisor', 'Cliente_Gerente'])) {
                    $actorClientId = (int)$u->id;
                } else {
                    $actorUserId = (int)$u->id;
                }
            }

            $payload = array_merge([
                'ip' => $req->ip(),
                'user_agent' => (string)$req->userAgent(),
            ], $payload);

            CotizacionAuditLog::create([
                'cotizacion_id' => (int)$cotizacion->id,
                'action' => $action,
                'actor_user_id' => $actorUserId,
                'actor_client_id' => $actorClientId,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            // No bloquear el flujo de negocio por fallas de auditoría
        }
    }

    private function enforceClientApprovalToken(Request $req, Cotizacion $cotizacion, bool $requireToken = false): void
    {
        $u = $req->user();
        if (!$u) return;

        // Solo exigir token a roles de cliente (staff puede acceder desde el sistema sin token)
        if (!$u->hasAnyRole(['Cliente_Supervisor', 'Cliente_Gerente'])) return;

        // Si aún no hay hash (legacy), no bloquear.
        if (!$cotizacion->approval_token_hash) return;

        // El token NO se exige para navegación interna (campanita). Si viene, se valida.
        $plain = $req->string('token')->toString();
        if ($plain === '') {
            if ($requireToken) {
                abort(403, 'Token requerido.');
            }
            return;
        }

        $svc = app(QuotationService::class);
        if (!$svc->approvalTokenMatches($cotizacion, $plain)) {
            abort(403, 'Token inválido.');
        }
    }

    private function allowedCentroIds(User $u): array
    {
        if ($u->hasRole('admin')) return [];
        $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v) => (int)$v)->all();
        $primary = (int)($u->centro_trabajo_id ?? 0);
        if ($primary) $ids[] = $primary;
        return array_values(array_unique(array_filter($ids)));
    }

    /** Folio tipo ABC-COT-YYYYMM-0001 */
    private function generarFolio(int $centroId): string
    {
        $centro = CentroTrabajo::find($centroId);
        $prefijo = $centro?->prefijo
            ?? ($centro && $centro->nombre
                ? strtoupper(substr(preg_replace('/[^a-z]/i', '', $centro->nombre), 0, 3))
                : 'UPR');

        $prefijo = strtoupper(substr($prefijo, 0, 10));
        $yyyymm = now()->format('Ym');

        $base = $prefijo . '-COT-' . $yyyymm . '-';

        $lastFolio = Cotizacion::where('folio', 'like', $base . '%')
            ->orderByDesc('folio')
            ->lockForUpdate()
            ->value('folio');

        $seq = 1;
        if (is_string($lastFolio) && preg_match('/-(\d{4})$/', $lastFolio, $m)) {
            $seq = ((int)$m[1]) + 1;
        }

        return sprintf('%s%04d', $base, $seq);
    }

    /** Folio de solicitudes (reusa formato actual ABC-YYYYMM-0001). */
    private function generarFolioSolicitud(int $centroId): string
    {
        $centro = CentroTrabajo::find($centroId);
        $prefijo = $centro?->prefijo
            ?? ($centro && $centro->nombre
                ? strtoupper(substr(preg_replace('/[^a-z]/i', '', $centro->nombre), 0, 3))
                : 'UPR');

        $prefijo = strtoupper(substr($prefijo, 0, 10));
        $yyyymm = now()->format('Ym');
        $base = $prefijo . '-' . $yyyymm . '-';

        $lastFolio = Solicitud::where('folio', 'like', $base . '%')
            ->orderByDesc('folio')
            ->lockForUpdate()
            ->value('folio');

        $seq = 1;
        if (is_string($lastFolio) && preg_match('/-(\d{4})$/', $lastFolio, $m)) {
            $seq = ((int)$m[1]) + 1;
        }

        return sprintf('%s%04d', $base, $seq);
    }
}
