<?php

namespace App\Http\Requests\Api\QuotationItemServices;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuotationItemServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\CotizacionItemServicio $line */
        $line = $this->route('cotizacionItemServicio');
        $cotizacion = $line?->item?->cotizacion;

        return $cotizacion ? ($this->user()?->can('update', $cotizacion) ?? false) : false;
    }

    public function rules(): array
    {
        return [
            'size' => ['sometimes', 'nullable', 'string', 'max:20'],

            'quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'qty' => ['sometimes', 'nullable', 'numeric', 'min:0.001'],

            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
