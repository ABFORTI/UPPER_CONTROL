<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('admin');
    }

    public function rules(): array
    {
        $visibleParaTodos = filter_var($this->input('visible_para_todos'), FILTER_VALIDATE_BOOLEAN);

        return [
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'archivo_pdf' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:20480',
            ],
            'visible_para_todos' => ['nullable', 'boolean'],
            'roles' => [
                Rule::requiredIf(! $visibleParaTodos),
                'array',
            ],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'roles.required' => 'Selecciona al menos un rol o marca visible para todos.',
            'archivo_pdf.mimes' => 'El archivo debe ser un PDF.',
            'archivo_pdf.mimetypes' => 'El archivo debe ser un PDF valido.',
            'archivo_pdf.max' => 'El archivo no debe superar 20 MB.',
        ];
    }
}
