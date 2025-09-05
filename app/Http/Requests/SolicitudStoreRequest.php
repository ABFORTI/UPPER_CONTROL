<?php

// app/Http/Requests/SolicitudStoreRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ServicioEmpresa;

class SolicitudStoreRequest extends FormRequest {
  public function authorize(): bool { return true; } // luego Policy real
  public function rules(): array {
    return [
      'id_servicio'       => ['required','integer','exists:servicios_empresa,id'],
      'cantidad'          => ['required','integer','min:1'],
      'descripcion'       => ['nullable','string','max:255'],
      'tamano'            => ['nullable','in:chico,mediano,grande'],
      'notas'             => ['nullable','string','max:2000'],
      'adjuntos'          => ['array'],
      'adjuntos.*'        => ['file','max:5120'], // 5MB
    ];
  }
  public function withValidator($validator) {
    $validator->after(function ($v) {
      $servicio = ServicioEmpresa::find($this->input('id_servicio'));
      if (!$servicio) return;
      if ($servicio->usa_tamanos) {
        if (!$this->input('tamano')) $v->errors()->add('tamano','El tamaño es obligatorio.');
      } else {
        if (!$this->input('descripcion')) $v->errors()->add('descripcion','La descripción es obligatoria.');
        if ($this->filled('tamano')) $v->errors()->add('tamano','Este servicio no usa tamaños.');
      }
    });
  }
}
