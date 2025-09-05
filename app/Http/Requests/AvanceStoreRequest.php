<?php

// app/Http/Requests/AvanceStoreRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AvanceStoreRequest extends FormRequest {
  public function authorize(): bool { return true; } // luego Policy real (debe ser TL de esa OT)
  public function rules(): array {
    return [
      'id_item'   => ['nullable','integer','exists:orden_items,id'],
      'cantidad'  => ['required','integer','min:1'],
      'comentario'=> ['nullable','string','max:1000'],
      'evidencia' => ['nullable','file','max:6144'] // 6MB
    ];
  }
}

