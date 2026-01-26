<?php

namespace App\Http\Controllers\Api;

use App\Domain\Servicios\PricingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Quotations\IndexQuotationsRequest;
use App\Http\Requests\Api\Quotations\SendQuotationRequest;
use App\Http\Requests\Api\Quotations\StoreQuotationItemRequest;
use App\Http\Requests\Api\Quotations\StoreQuotationRequest;
use App\Http\Requests\Api\Quotations\UpdateQuotationRequest;
use App\Http\Resources\QuotationItemResource;
use App\Http\Resources\QuotationResource;
use App\Models\Area;
use App\Models\CentroCosto;
use App\Models\CentroTrabajo;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\Marca;
use App\Models\ServicioCentro;
use App\Models\User;
use App\Services\Notifier;
use App\Services\QuotationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Notifications\QuotationSentNotification;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class QuotationController extends Controller
{
    private const DEFAULT_EXPIRES_DAYS = 7;
    private const TAX_RATE = 0.16;

    public function index(IndexQuotationsRequest $req)
    {
        $filters = $req->validated();
        $perPage = (int)($filters['per_page'] ?? 15);

        /** @var User $u */
        $u = $req->user();

        $q = Cotizacion::query()
            ->with([
                'cliente:id,name,email',
                'centro:id,nombre,prefijo',
                'centroCosto:id,nombre',
                'marca:id,nombre',
            ])
            ->orderByDesc('id');

        if (!empty($filters['status'])) {
            $q->where('estatus', $filters['status']);
        }

        if (!empty($filters['client_id'])) {
            $q->where('id_cliente', (int)$filters['client_id']);
        }

        if (!empty($filters['date_from'])) {
            $q->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $q->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['q'])) {
            $term = trim((string)$filters['q']);
            $q->where(function ($qq) use ($term) {
                $qq->where('folio', 'like', '%' . $term . '%')
                    ->orWhere('notas', 'like', '%' . $term . '%')
                    ->orWhere('notes', 'like', '%' . $term . '%')
                    ->orWhereHas('items', function ($qi) use ($term) {
                        $qi->where('descripcion', 'like', '%' . $term . '%');
                    });
            });
        }

        // Alcance: coordinador solo ve su centro; admin ve todo
        if (!$u->hasRole('admin')) {
            $q->where('id_centrotrabajo', (int)($u->centro_trabajo_id ?? 0));
        }

        $paginator = $q->paginate($perPage)->withQueryString();

        return QuotationResource::collection($paginator);
    }

    public function store(StoreQuotationRequest $req)
    {
        $data = $req->validated();

        /** @var User $u */
        $u = $req->user();

        $centroId = $u->hasRole('admin')
            ? (int)($data['centro_trabajo_id'] ?? ($u->centro_trabajo_id ?? 0))
            : (int)($u->centro_trabajo_id ?? 0);

        if (!$centroId) {
            throw ValidationException::withMessages(['centro_trabajo_id' => 'No se pudo determinar el centro de trabajo.']);
        }

        $client = User::findOrFail((int)$data['client_id']);
        if (!$client->hasAnyRole(['Cliente_Supervisor', 'Cliente_Gerente'])) {
            throw ValidationException::withMessages(['client_id' => 'El usuario seleccionado no tiene rol de cliente.']);
        }
        if (!$u->hasRole('admin') && (int)$client->centro_trabajo_id !== $centroId) {
            throw ValidationException::withMessages(['client_id' => 'El cliente no pertenece a tu centro de trabajo.']);
        }

        $cc = CentroCosto::find((int)$data['centro_costo_id']);
        if (!$cc || (int)$cc->id_centrotrabajo !== $centroId) {
            throw ValidationException::withMessages(['centro_costo_id' => 'El centro de costos no pertenece al centro seleccionado.']);
        }

        if (array_key_exists('brand_id', $data) && $data['brand_id'] !== null) {
            $marca = Marca::find((int)$data['brand_id']);
            if (!$marca || (int)$marca->id_centrotrabajo !== $centroId) {
                throw ValidationException::withMessages(['brand_id' => 'La marca seleccionada no pertenece al centro seleccionado.']);
            }
        }

        if (array_key_exists('area_id', $data) && $data['area_id'] !== null) {
            $area = Area::find((int)$data['area_id']);
            if (!$area || (int)$area->id_centrotrabajo !== $centroId) {
                throw ValidationException::withMessages(['area_id' => 'El área seleccionada no pertenece al centro seleccionado.']);
            }
        }

        $cotizacion = DB::transaction(function () use ($u, $data, $centroId) {
            return Cotizacion::create([
                'folio' => $this->generateFolioForCentro($centroId),
                'created_by' => $u->id,
                'id_cliente' => (int)$data['client_id'],
                'id_centrotrabajo' => $centroId,
                'id_centrocosto' => (int)$data['centro_costo_id'],
                'id_marca' => $data['brand_id'] ?? null,
                'id_area' => $data['area_id'] ?? null,
                'currency' => strtoupper((string)($data['currency'] ?? 'MXN')),
                'notas' => $data['notas'] ?? null,
                'notes' => $data['notes'] ?? null,
                'estatus' => Cotizacion::ESTATUS_DRAFT,
                'subtotal' => 0,
                'iva' => 0,
                'tax' => 0,
                'total' => 0,
            ]);
        });

        $cotizacion->load(['cliente:id,name,email', 'centro:id,nombre,prefijo', 'centroCosto:id,nombre', 'marca:id,nombre', 'area:id,nombre']);

        return (new QuotationResource($cotizacion))->response()->setStatusCode(201);
    }

    /** Folio tipo ABC-COT-YYYYMM-0001 (alineado al módulo web). */
    private function generateFolioForCentro(int $centroId): string
    {
        $centro = CentroTrabajo::find($centroId);
        $prefijo = $centro?->prefijo
            ?? ($centro && $centro->nombre
                ? strtoupper(substr(preg_replace('/[^a-z]/i', '', $centro->nombre), 0, 3))
                : 'UPR');

        $prefijo = strtoupper(substr((string)$prefijo, 0, 10));
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

    public function show(Cotizacion $cotizacion)
    {
        $this->authorize('view', $cotizacion);

        $cotizacion->load([
            'cliente:id,name,email',
            'centro:id,nombre,prefijo',
            'centroCosto:id,nombre',
            'marca:id,nombre',
            'area:id,nombre',
            'items.servicios.servicio:id,nombre,usa_tamanos',
        ]);

        return new QuotationResource($cotizacion);
    }

    public function pdf(Cotizacion $cotizacion)
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

    public function update(UpdateQuotationRequest $req, Cotizacion $cotizacion)
    {
        $this->authorize('update', $cotizacion);

        if ($cotizacion->estatus !== Cotizacion::ESTATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Solo puedes editar cotizaciones en draft.']);
        }

        $data = $req->validated();

        $centroId = (int)$cotizacion->id_centrotrabajo;

        if (array_key_exists('client_id', $data)) {
            $client = User::findOrFail((int)$data['client_id']);
            if (!$client->hasAnyRole(['Cliente_Supervisor', 'Cliente_Gerente'])) {
                throw ValidationException::withMessages(['client_id' => 'El usuario seleccionado no tiene rol de cliente.']);
            }
            if (!$req->user()->hasRole('admin') && (int)$client->centro_trabajo_id !== $centroId) {
                throw ValidationException::withMessages(['client_id' => 'El cliente no pertenece al centro de la cotización.']);
            }
            $cotizacion->id_cliente = (int)$data['client_id'];
        }

        if (array_key_exists('centro_costo_id', $data)) {
            $cc = CentroCosto::find((int)$data['centro_costo_id']);
            if (!$cc || (int)$cc->id_centrotrabajo !== $centroId) {
                throw ValidationException::withMessages(['centro_costo_id' => 'El centro de costos no pertenece al centro de la cotización.']);
            }
            $cotizacion->id_centrocosto = (int)$data['centro_costo_id'];
        }

        if (array_key_exists('brand_id', $data)) {
            if ($data['brand_id'] === null) {
                $cotizacion->id_marca = null;
            } else {
                $marca = Marca::find((int)$data['brand_id']);
                if (!$marca || (int)$marca->id_centrotrabajo !== $centroId) {
                    throw ValidationException::withMessages(['brand_id' => 'La marca no pertenece al centro de la cotización.']);
                }
                $cotizacion->id_marca = (int)$data['brand_id'];
            }
        }

        if (array_key_exists('area_id', $data)) {
            if ($data['area_id'] === null) {
                $cotizacion->id_area = null;
            } else {
                $area = Area::find((int)$data['area_id']);
                if (!$area || (int)$area->id_centrotrabajo !== $centroId) {
                    throw ValidationException::withMessages(['area_id' => 'El área no pertenece al centro de la cotización.']);
                }
                $cotizacion->id_area = (int)$data['area_id'];
            }
        }

        if (array_key_exists('currency', $data)) {
            $cotizacion->currency = strtoupper((string)$data['currency']);
        }

        if (array_key_exists('notes', $data)) {
            $cotizacion->notes = $data['notes'];
        }
        if (array_key_exists('notas', $data)) {
            $cotizacion->notas = $data['notas'];
        }

        $cotizacion->save();
        $cotizacion->load([
            'cliente:id,name,email',
            'centro:id,nombre,prefijo',
            'centroCosto:id,nombre',
            'marca:id,nombre',
            'area:id,nombre',
            'items.servicios.servicio:id,nombre,usa_tamanos',
        ]);

        return new QuotationResource($cotizacion);
    }

    public function storeItem(StoreQuotationItemRequest $req, Cotizacion $cotizacion)
    {
        $this->authorize('update', $cotizacion);

        if ($cotizacion->estatus !== Cotizacion::ESTATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Solo puedes modificar ítems en cotizaciones draft.']);
        }

        $data = $req->validated();

        $item = CotizacionItem::create([
            'cotizacion_id' => (int)$cotizacion->id,
            'descripcion' => $data['description'],
            'cantidad' => (int)$data['quantity'],
            'notas' => $data['notes'] ?? null,

            'product_name' => $data['product_name'] ?? null,
            'quantity' => $data['quantity_decimal'] ?? null,
            'unit' => $data['unit'] ?? 'pz',
            'centro_costo_id' => $data['centro_costo_id'] ?? null,
            'brand_id' => $data['brand_id'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        $item->load(['servicios.servicio:id,nombre,usa_tamanos']);

        return (new QuotationItemResource($item))->response()->setStatusCode(201);
    }

    public function send(SendQuotationRequest $req, Cotizacion $cotizacion)
    {
        $this->authorize('send', $cotizacion);

        if ($cotizacion->estatus !== Cotizacion::ESTATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Solo puedes enviar cotizaciones en draft.']);
        }

        // Evitar enviar una cotización vacía
        $cotizacion->loadCount(['items', 'items as services_count' => function ($q) {
            $q->whereHas('servicios');
        }]);
        if ((int)($cotizacion->items_count ?? 0) <= 0) {
            throw ValidationException::withMessages(['items' => 'La cotización debe tener al menos un ítem.']);
        }

        $cotizacion->loadMissing(['items.servicios']);
        $hasAnyService = false;
        foreach ($cotizacion->items as $it) {
            if (($it->servicios?->count() ?? 0) > 0) {
                $hasAnyService = true;
                break;
            }
        }
        if (!$hasAnyService) {
            throw ValidationException::withMessages(['services' => 'La cotización debe tener al menos un servicio.']);
        }

        $cotizacion->loadMissing(['cliente:id,name,email']);

        $expiresDays = (int)($req->validated()['expires_days'] ?? self::DEFAULT_EXPIRES_DAYS);
        $expiresAt = now()->addDays($expiresDays);

        $token = DB::transaction(function () use ($cotizacion, $expiresAt) {
            $locked = Cotizacion::whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();
            if ($locked->estatus !== Cotizacion::ESTATUS_DRAFT) {
                throw ValidationException::withMessages(['status' => 'La cotización ya no está en draft.']);
            }

            $locked->update([
                'estatus' => Cotizacion::ESTATUS_SENT,
                'sent_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            return app(QuotationService::class)->generateApprovalToken($locked);
        });

        $cotizacion->refresh();

        // URL solicitada para cliente (token solo para correo / respuesta API; NO se almacena en database notifications)
        $url = route('client.public.quotations.show', [
            'cotizacion' => $cotizacion->id,
            'token' => $token,
        ]);

        try {
            $itemsCount = (int)($cotizacion->items()->count());
            $cotizacion->cliente?->notify(new QuotationSentNotification(
                $cotizacion,
                $token,
                $itemsCount,
                $expiresAt->toISOString(),
            ));
        } catch (\Throwable $e) {
            Log::warning('API: fallo al notificar cotización enviada (ignorado)', [
                'cotizacion_id' => $cotizacion->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        $cotizacion->load([
            'cliente:id,name,email',
            'centro:id,nombre,prefijo',
            'centroCosto:id,nombre',
            'marca:id,nombre',
            'area:id,nombre',
            'items.servicios.servicio:id,nombre,usa_tamanos',
        ]);

        return response()->json([
            'data' => (new QuotationResource($cotizacion))->toArray($req),
            'review_url' => $url,
            'expires_at' => $expiresAt->toISOString(),
        ]);
    }
}
