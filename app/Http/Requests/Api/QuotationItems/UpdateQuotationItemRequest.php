<?php

namespace App\Http\Requests\Api\QuotationItems;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuotationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\CotizacionItem $item */
        $item = $this->route('cotizacionItem');
        $cotizacion = $item?->cotizacion;

        return $cotizacion ? ($this->user()?->can('update', $cotizacion) ?? false) : false;
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'string', 'max:255'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],

            'product_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'quantity_decimal' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'centro_costo_id' => ['sometimes', 'nullable', 'integer', 'exists:centros_costos,id'],
            'brand_id' => ['sometimes', 'nullable', 'integer', 'exists:marcas,id'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
