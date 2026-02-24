<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OtCortePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'coordinador', 'team_leader', 'facturacion']) === true;
    }

    public function rules(): array
    {
        return [
            'periodo_inicio' => ['nullable', 'date', 'before_or_equal:periodo_fin'],
            'periodo_fin'    => ['nullable', 'date', 'after_or_equal:periodo_inicio'],
        ];
    }

    public function messages(): array
    {
        return [
            'periodo_inicio.before_or_equal' => 'La fecha de inicio debe ser anterior o igual a la fecha de fin.',
            'periodo_fin.after_or_equal'     => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
        ];
    }
}
