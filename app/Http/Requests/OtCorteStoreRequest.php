<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OtCorteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'coordinador', 'team_leader', 'facturacion']) === true;
    }

    public function rules(): array
    {
        return [
            'periodo_inicio'               => ['required', 'date'],
            'periodo_fin'                   => ['required', 'date', 'after_or_equal:periodo_inicio'],
            'crear_ot_hija'                => ['sometimes', 'boolean'],
            'detalles'                     => ['required', 'array', 'min:1'],
            'detalles.*.ot_servicio_id'    => ['nullable', 'integer', 'exists:ot_servicios,id'],
            'detalles.*.orden_item_id'     => ['nullable', 'integer', 'exists:orden_items,id'],
            'detalles.*.cantidad_cortada'  => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'periodo_inicio.required'            => 'La fecha de inicio del periodo es obligatoria.',
            'periodo_fin.required'               => 'La fecha de fin del periodo es obligatoria.',
            'periodo_fin.after_or_equal'          => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'detalles.required'                  => 'Debe incluir al menos un concepto en el corte.',
            'detalles.min'                       => 'Debe incluir al menos un concepto en el corte.',
            'detalles.*.ot_servicio_id.exists'   => 'El servicio seleccionado no existe.',
            'detalles.*.orden_item_id.exists'     => 'El item seleccionado no existe.',
            'detalles.*.cantidad_cortada.required' => 'La cantidad a cortar es obligatoria.',
            'detalles.*.cantidad_cortada.min'    => 'La cantidad a cortar no puede ser negativa.',
        ];
    }
}
