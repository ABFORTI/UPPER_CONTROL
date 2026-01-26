<?php

namespace App\Http\Controllers\Api;

use App\Domain\Servicios\PricingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QuotationItemServices\StoreQuotationItemServiceRequest;
use App\Http\Requests\Api\QuotationItemServices\UpdateQuotationItemServiceRequest;
use App\Http\Resources\QuotationItemServiceResource;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\CotizacionItemServicio;
use App\Models\ServicioCentro;
use App\Services\QuotationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationItemServiceController extends Controller
{
    private const TAX_RATE = 0.16;

    public function store(StoreQuotationItemServiceRequest $req, CotizacionItem $cotizacionItem)
    {
        $cotizacion = $cotizacionItem->cotizacion;
        if (!$cotizacion) abort(404);

        $this->authorize('update', $cotizacion);

        if ($cotizacion->estatus !== Cotizacion::ESTATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Solo puedes modificar servicios en cotizaciones draft.']);
        }

        $data = $req->validated();

        $centroId = (int)$cotizacion->id_centrotrabajo;
        $servicioId = (int)$data['service_id'];
        $tamano = isset($data['size']) && $data['size'] !== '' ? (string)$data['size'] : null;

        $hasConfig = ServicioCentro::where('id_centrotrabajo', $centroId)
            ->where('id_servicio', $servicioId)
            ->exists();
        if (!$hasConfig) {
            throw ValidationException::withMessages(['service_id' => 'El servicio no tiene precio configurado para este centro.']);
        }

        $qtyDecimal = array_key_exists('qty', $data) && $data['qty'] !== null ? (float)$data['qty'] : null;
        $cantidadInt = array_key_exists('quantity', $data) && $data['quantity'] !== null ? (int)$data['quantity'] : null;

        if ($qtyDecimal === null && $cantidadInt === null) {
            // fallback: usa cantidad del ítem
            $cantidadInt = (int)($cotizacionItem->cantidad ?? 1);
        }

        $pricing = app(PricingService::class);
        $pu = (float)$pricing->precioUnitario($centroId, $servicioId, $tamano);

        $effectiveQty = $qtyDecimal !== null ? $qtyDecimal : (float)($cantidadInt ?? 1);
        if ($effectiveQty <= 0) $effectiveQty = 1;

        $sub = $pu * $effectiveQty;
        $tax = $sub * self::TAX_RATE;
        $tot = $sub + $tax;

        $line = CotizacionItemServicio::create([
            'cotizacion_item_id' => (int)$cotizacionItem->id,
            'id_servicio' => $servicioId,
            'tamano' => $tamano,
            'tamanos_json' => null,
            'cantidad' => $cantidadInt ?? (int)($cotizacionItem->cantidad ?? 1),
            'qty' => $qtyDecimal,
            'precio_unitario' => $pu,
            'subtotal' => $sub,
            'iva' => $tax,
            'total' => $tot,
            'notes' => $data['notes'] ?? null,
        ]);

        // Recalcular totals para mantener consistencia
        app(QuotationService::class)->recalculateTotals($cotizacion, self::TAX_RATE);

        $line->load(['servicio:id,nombre,usa_tamanos']);

        return (new QuotationItemServiceResource($line))->response()->setStatusCode(201);
    }

    public function update(UpdateQuotationItemServiceRequest $req, CotizacionItemServicio $cotizacionItemServicio)
    {
        $item = $cotizacionItemServicio->item;
        $cotizacion = $item?->cotizacion;
        if (!$cotizacion) abort(404);

        $this->authorize('update', $cotizacion);

        if ($cotizacion->estatus !== Cotizacion::ESTATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Solo puedes editar servicios en cotizaciones draft.']);
        }

        $data = $req->validated();

        if (array_key_exists('notes', $data)) {
            $cotizacionItemServicio->notes = $data['notes'];
        }

        if (array_key_exists('size', $data)) {
            $cotizacionItemServicio->tamano = $data['size'] !== null && $data['size'] !== '' ? (string)$data['size'] : null;
        }

        $qtyDecimal = array_key_exists('qty', $data) ? ($data['qty'] !== null ? (float)$data['qty'] : null) : $cotizacionItemServicio->qty;
        $cantidadInt = array_key_exists('quantity', $data) ? ($data['quantity'] !== null ? (int)$data['quantity'] : null) : $cotizacionItemServicio->cantidad;

        // Si setean qty, podemos mantener cantidad por compatibilidad
        if (array_key_exists('qty', $data)) {
            $cotizacionItemServicio->qty = $qtyDecimal;
        }
        if (array_key_exists('quantity', $data) && $data['quantity'] !== null) {
            $cotizacionItemServicio->cantidad = $cantidadInt;
            // Si prefieren cantidad int, limpiamos qty
            if (!array_key_exists('qty', $data)) {
                $cotizacionItemServicio->qty = null;
            }
        }

        // Recalcular precio_unitario si cambió tamano
        $centroId = (int)$cotizacion->id_centrotrabajo;
        $servicioId = (int)$cotizacionItemServicio->id_servicio;
        $tamano = $cotizacionItemServicio->tamano ? (string)$cotizacionItemServicio->tamano : null;

        $pricing = app(PricingService::class);
        $cotizacionItemServicio->precio_unitario = (float)$pricing->precioUnitario($centroId, $servicioId, $tamano);

        $cotizacionItemServicio->save();

        app(QuotationService::class)->recalculateTotals($cotizacion, self::TAX_RATE);

        $cotizacionItemServicio->refresh();
        $cotizacionItemServicio->load(['servicio:id,nombre,usa_tamanos']);

        return new QuotationItemServiceResource($cotizacionItemServicio);
    }

    public function destroy(CotizacionItemServicio $cotizacionItemServicio)
    {
        $item = $cotizacionItemServicio->item;
        $cotizacion = $item?->cotizacion;
        if (!$cotizacion) abort(404);

        $this->authorize('update', $cotizacion);

        if ($cotizacion->estatus !== Cotizacion::ESTATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Solo puedes eliminar servicios en cotizaciones draft.']);
        }

        $cotizacionItemServicio->delete();

        app(QuotationService::class)->recalculateTotals($cotizacion, self::TAX_RATE);

        return response()->noContent();
    }
}
