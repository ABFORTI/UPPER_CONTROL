<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOtDetalleAjusteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ot = $this->route('ot');
        return $this->user() !== null && $ot && $this->user()->can('registrarAjuste', $ot);
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'in:extra,faltante'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:2000', 'required_if:tipo,extra'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'El tipo de ajuste es obligatorio.',
            'tipo.in' => 'El tipo de ajuste es inválido.',
            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.integer' => 'La cantidad debe ser entera.',
            'cantidad.min' => 'La cantidad debe ser mayor a cero.',
            'motivo.required_if' => 'El motivo es obligatorio cuando el tipo es extra.',
        ];
    }
}
