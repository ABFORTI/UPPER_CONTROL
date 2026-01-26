<?php

namespace App\Http\Requests\Api\QuotationItemServices;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationItemServiceRequest extends FormRequest
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
            'service_id' => ['required', 'integer', 'exists:servicios_empresa,id'],
            'size' => ['nullable', 'string', 'max:20'],

            // usar uno u otro
            'quantity' => ['nullable', 'integer', 'min:1'],
            'qty' => ['nullable', 'numeric', 'min:0.001'],

            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
