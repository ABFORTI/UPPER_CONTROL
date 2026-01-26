<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QuotationItems\UpdateQuotationItemRequest;
use App\Http\Resources\QuotationItemResource;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Services\QuotationService;
use Illuminate\Validation\ValidationException;

class QuotationItemController extends Controller
{
    public function update(UpdateQuotationItemRequest $req, CotizacionItem $cotizacionItem)
    {
        $cotizacion = $cotizacionItem->cotizacion;
        if (!$cotizacion) abort(404);

        $this->authorize('update', $cotizacion);

        if ($cotizacion->estatus !== Cotizacion::ESTATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Solo puedes editar ítems en cotizaciones draft.']);
        }

        $data = $req->validated();

        if (array_key_exists('description', $data)) $cotizacionItem->descripcion = $data['description'];
        if (array_key_exists('quantity', $data)) $cotizacionItem->cantidad = (int)$data['quantity'];
        if (array_key_exists('notes', $data)) $cotizacionItem->notas = $data['notes'];

        if (array_key_exists('product_name', $data)) $cotizacionItem->product_name = $data['product_name'];
        if (array_key_exists('quantity_decimal', $data)) $cotizacionItem->quantity = $data['quantity_decimal'];
        if (array_key_exists('unit', $data)) $cotizacionItem->unit = $data['unit'] ?? 'pz';
        if (array_key_exists('centro_costo_id', $data)) $cotizacionItem->centro_costo_id = $data['centro_costo_id'];
        if (array_key_exists('brand_id', $data)) $cotizacionItem->brand_id = $data['brand_id'];
        if (array_key_exists('metadata', $data)) $cotizacionItem->metadata = $data['metadata'];

        $cotizacionItem->save();
        $cotizacionItem->load(['servicios.servicio:id,nombre,usa_tamanos']);

        return new QuotationItemResource($cotizacionItem);
    }

    public function destroy(CotizacionItem $cotizacionItem)
    {
        $cotizacion = $cotizacionItem->cotizacion;
        if (!$cotizacion) abort(404);

        $this->authorize('update', $cotizacion);

        if ($cotizacion->estatus !== Cotizacion::ESTATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Solo puedes eliminar ítems en cotizaciones draft.']);
        }

        $cotizacionItem->delete();

        app(QuotationService::class)->recalculateTotals($cotizacion);

        return response()->noContent();
    }
}
