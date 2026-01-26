<?php

namespace App\Http\Requests\Api\Quotations;

use Illuminate\Foundation\Http\FormRequest;

class SendQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\Cotizacion $cotizacion */
        $cotizacion = $this->route('cotizacion');
        return $cotizacion ? ($this->user()?->can('send', $cotizacion) ?? false) : false;
    }

    public function rules(): array
    {
        return [
            'expires_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }
}
