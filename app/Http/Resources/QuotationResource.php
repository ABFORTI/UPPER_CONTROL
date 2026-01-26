<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'folio' => $this->folio,
            'status' => $this->estatus,
            'currency' => $this->currency,

            'created_by' => $this->created_by,
            'client_id' => $this->id_cliente,
            'centro_trabajo_id' => $this->id_centrotrabajo,
            'centro_costo_id' => $this->id_centrocosto,
            'brand_id' => $this->id_marca,
            'area_id' => $this->id_area,

            'client' => $this->whenLoaded('cliente', fn () => [
                'id' => $this->cliente?->id,
                'name' => $this->cliente?->name,
                'email' => $this->cliente?->email,
            ]),
            'centro' => $this->whenLoaded('centro', fn () => [
                'id' => $this->centro?->id,
                'name' => $this->centro?->nombre,
                'prefix' => $this->centro?->prefijo,
            ]),
            'centro_costo' => $this->whenLoaded('centroCosto', fn () => [
                'id' => $this->centroCosto?->id,
                'name' => $this->centroCosto?->nombre,
            ]),
            'brand' => $this->whenLoaded('marca', fn () => [
                'id' => $this->marca?->id,
                'name' => $this->marca?->nombre,
            ]),
            'area' => $this->whenLoaded('area', fn () => [
                'id' => $this->area?->id,
                'name' => $this->area?->nombre,
            ]),

            'subtotal' => (float)($this->subtotal ?? 0),
            'tax' => (float)($this->tax ?? $this->iva ?? 0),
            'iva' => (float)($this->iva ?? 0),
            'total' => (float)($this->total ?? 0),

            // compatibilidad
            'notas' => $this->notas,
            'notes' => $this->notes,
            'reject_reason' => $this->motivo_rechazo,

            'sent_at' => optional($this->sent_at)->toISOString(),
            'approved_at' => optional($this->approved_at)->toISOString(),
            'rejected_at' => optional($this->rejected_at)->toISOString(),
            'cancelled_at' => optional($this->cancelled_at)->toISOString(),
            'expires_at' => optional($this->expires_at)->toISOString(),

            'items' => QuotationItemResource::collection($this->whenLoaded('items')),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
