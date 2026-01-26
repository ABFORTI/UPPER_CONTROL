<?php

namespace App\Http\Requests\Api\Quotations;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuotationRequest extends FormRequest
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
            'client_id' => ['sometimes', 'integer', 'exists:users,id'],
            'centro_costo_id' => ['sometimes', 'integer', 'exists:centros_costos,id'],
            'brand_id' => ['sometimes', 'nullable', 'integer', 'exists:marcas,id'],
            'area_id' => ['sometimes', 'nullable', 'integer', 'exists:areas,id'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notas' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
