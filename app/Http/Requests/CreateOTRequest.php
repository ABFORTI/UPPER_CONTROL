<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOTRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el controlador
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Datos del header (una sola vez)
            'header.centro_trabajo_id' => ['required', 'integer', 'exists:centros_trabajo,id'],
            'header.centro_costos_id' => ['nullable', 'integer', 'exists:centros_costos,id'],
            'header.marca_id' => ['nullable', 'integer', 'exists:marcas,id'],
            'header.area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'header.descripcion_producto' => ['required', 'string', 'max:500'],
            'header.cliente_id' => ['nullable', 'integer', 'exists:users,id'],
            'header.team_leader_id' => ['nullable', 'integer', 'exists:users,id'],

            // Array de servicios (mínimo 1)
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*.servicio_id' => ['required', 'integer', 'exists:servicios_empresa,id'],
            'servicios.*.tipo_cobro' => ['required', 'string', 'max:50'],
            'servicios.*.cantidad' => ['required', 'integer', 'min:1'],
            'servicios.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'header.centro_trabajo_id' => 'centro de trabajo',
            'header.centro_costos_id' => 'centro de costos',
            'header.marca_id' => 'marca',
            'header.area_id' => 'área',
            'header.descripcion_producto' => 'descripción del producto',
            'header.cliente_id' => 'cliente',
            'header.team_leader_id' => 'team leader',
            'servicios' => 'servicios',
            'servicios.*.servicio_id' => 'servicio',
            'servicios.*.tipo_cobro' => 'tipo de cobro',
            'servicios.*.cantidad' => 'cantidad',
            'servicios.*.precio_unitario' => 'precio unitario',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'servicios.min' => 'Debe agregar al menos un servicio.',
            'servicios.*.servicio_id.required' => 'El servicio es requerido.',
            'servicios.*.servicio_id.exists' => 'El servicio seleccionado no es válido.',
            'servicios.*.tipo_cobro.required' => 'El tipo de cobro es requerido.',
            'servicios.*.cantidad.required' => 'La cantidad es requerida.',
            'servicios.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
            'servicios.*.precio_unitario.required' => 'El precio unitario es requerido.',
            'servicios.*.precio_unitario.min' => 'El precio unitario debe ser mayor o igual a 0.',
        ];
    }
}
