<?php

// app/Http/Requests/OrdenFromSolicitudRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ServicioEmpresa;

class OrdenFromSolicitudRequest extends FormRequest {
  public function authorize(): bool { return true; } // luego Policy real
  public function rules(): array {
    return [
      'team_leader_id'   => ['nullable','integer','exists:users,id'],
      'id_servicio'      => ['required','integer','exists:servicios_empresa,id'],
      'id_centro'        => ['required','integer','exists:centros_trabajo,id'],
      'items'            => ['required','array','min:1'],
      'items.*.cantidad' => ['required','integer','min:1'],
      'items.*.descripcion' => ['nullable','string','max:255'],
      'items.*.tamano'      => ['nullable','in:chico,mediano,grande'],
      'items.*.sku'         => ['nullable','string','max:60'],
      'items.*.marca'       => ['nullable','string','max:60'],
    ];
  }
  public function withValidator($validator) {
    $validator->after(function ($v) {
      $servicio = ServicioEmpresa::find($this->input('id_servicio'));
      if (!$servicio) return;
      foreach ((array)$this->input('items',[]) as $i=>$item) {
        if ($servicio->usa_tamanos) {
          if (empty($item['tamano'])) $v->errors()->add("items.$i.tamano",'El tamaño es obligatorio.');
        } else {
          if (empty($item['descripcion'])) $v->errors()->add("items.$i.descripcion",'La descripción es obligatoria.');
          if (!empty($item['tamano'])) $v->errors()->add("items.$i.tamano",'Este servicio no acepta tamaños.');
        }
      }
    });
  }
}
