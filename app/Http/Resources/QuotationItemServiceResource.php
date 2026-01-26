<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationItemServiceResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'quotation_item_id' => $this->cotizacion_item_id,
            'service_id' => $this->id_servicio,
            'service' => $this->whenLoaded('servicio', fn () => [
                'id' => $this->servicio?->id,
                'name' => $this->servicio?->nombre,
                'uses_sizes' => (bool)($this->servicio?->usa_tamanos ?? false),
            ]),
            'size' => $this->tamano,
            'quantity' => $this->cantidad,
            'qty' => $this->qty,
            'unit_price' => (float)($this->precio_unitario ?? 0),
            'subtotal' => (float)($this->subtotal ?? 0),
            'tax' => (float)($this->iva ?? 0),
            'total' => (float)($this->total ?? 0),
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
