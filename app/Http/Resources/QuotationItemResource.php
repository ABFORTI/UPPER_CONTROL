<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationItemResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'quotation_id' => $this->cotizacion_id,

            'description' => $this->descripcion,
            'quantity' => $this->cantidad,
            'notes' => $this->notas,

            // Campos extendidos (compatibles)
            'product_name' => $this->product_name,
            'quantity_decimal' => $this->quantity,
            'unit' => $this->unit,
            'centro_costo_id' => $this->centro_costo_id,
            'brand_id' => $this->brand_id,
            'metadata' => $this->metadata,

            'services' => QuotationItemServiceResource::collection($this->whenLoaded('servicios')),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
