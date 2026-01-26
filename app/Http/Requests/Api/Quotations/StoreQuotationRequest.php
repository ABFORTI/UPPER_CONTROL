<?php

namespace App\Http\Requests\Api\Quotations;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Cotizacion::class) ?? false;
    }

    public function rules(): array
    {
        $isAdmin = $this->user()?->hasRole('admin') ?? false;

        return [
            // admin puede elegir centro; coordinador se toma de su centro
            'centro_trabajo_id' => $isAdmin ? ['nullable', 'integer', 'exists:centros_trabajo,id'] : ['nullable'],

            'client_id' => ['required', 'integer', 'exists:users,id'],
            'centro_costo_id' => ['required', 'integer', 'exists:centros_costos,id'],
            'brand_id' => ['nullable', 'integer', 'exists:marcas,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],

            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'notas' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
