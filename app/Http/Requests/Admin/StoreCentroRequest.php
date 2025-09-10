<?php

// app/Http/Requests/Admin/StoreCentroRequest.php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;

class StoreCentroRequest extends FormRequest {
    public function authorize(): bool { return $this->user()->hasRole('admin'); }
    public function rules(): array {
        return [
            'nombre' => ['required','string','max:120'],
            'prefijo'=> ['nullable','string','max:10'],
            'direccion'=> ['nullable','string','max:255'],
            'activo' => ['boolean'],
        ];
    }
}