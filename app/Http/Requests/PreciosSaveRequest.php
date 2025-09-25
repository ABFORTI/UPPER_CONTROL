<?php

// app/Http/Requests/PreciosSaveRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreciosSaveRequest extends FormRequest {
  public function authorize(): bool { return true; } // protegido por middleware de rol
  public function rules(): array {
    return [
      'id_centro' => ['required','integer','exists:centros_trabajo,id'],
      'items' => ['required','array','min:1'],
      'items.*.id_servicio' => ['required','integer','exists:servicios_empresa,id'],
      'items.*.usa_tamanos' => ['required','boolean'],
      'items.*.precio_base' => ['nullable','numeric','min:0'],
      'items.*.tamanos' => ['nullable','array'],
      'items.*.tamanos.chico' => ['nullable','numeric','min:0'],
      'items.*.tamanos.mediano' => ['nullable','numeric','min:0'],
      'items.*.tamanos.grande' => ['nullable','numeric','min:0'],
      'items.*.tamanos.jumbo' => ['nullable','numeric','min:0'],
    ];
  }
}
