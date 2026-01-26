<?php

namespace App\Http\Requests\Api\Quotations;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\Cotizacion $cotizacion */
        $cotizacion = $this->route('cotizacion');
        return $cotizacion ? ($this->user()?->can('update', $cotizacion) ?? false) : false;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],

            // extendidos
            'product_name' => ['nullable', 'string', 'max:255'],
            'quantity_decimal' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'centro_costo_id' => ['nullable', 'integer', 'exists:centros_costos,id'],
            'brand_id' => ['nullable', 'integer', 'exists:marcas,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
