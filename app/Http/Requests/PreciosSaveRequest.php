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

  public function withValidator($validator): void {
    $validator->after(function ($v) {
      $items = (array) $this->input('items', []);

      foreach ($items as $i => $item) {
        $usaTamanos = filter_var($item['usa_tamanos'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($usaTamanos) {
          foreach (['chico','mediano','grande','jumbo'] as $tamano) {
            $value = data_get($item, "tamanos.{$tamano}");
            if ($value === null || $value === '') {
              $v->errors()->add("items.{$i}.tamanos.{$tamano}", "El precio {$tamano} es obligatorio cuando el servicio usa tamanos.");
            }
          }
          continue;
        }

        $precioBase = data_get($item, 'precio_base');
        if ($precioBase === null || $precioBase === '') {
          $v->errors()->add("items.{$i}.precio_base", 'El precio unitario es obligatorio cuando el servicio no usa tamanos.');
        }
      }
    });
  }
}
