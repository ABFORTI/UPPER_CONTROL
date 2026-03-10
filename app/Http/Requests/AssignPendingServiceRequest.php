<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignPendingServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['admin', 'coordinador', 'team_leader']);
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:servicios_empresa,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required' => 'Debe seleccionar un servicio.',
            'service_id.exists' => 'El servicio seleccionado no existe en el catálogo.',
        ];
    }
}
