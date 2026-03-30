<?php

namespace App\Http\Requests\Api\Integraciones;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class StoreEtiquetasCvaGdlSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autenticacion se controla por middleware auth:sanctum.
        return true;
    }

    public function rules(): array
    {
        return [
            'referencia_externa' => ['required', 'string', 'max:255'],
            'origen' => ['nullable', 'string', 'max:100'],
            'sede' => ['nullable', 'string', 'max:20'],
            'paqueteria' => ['nullable', 'string', 'max:255'],
            'numero_factura' => ['nullable', 'string', 'max:255'],
            'numero_cajas' => ['required', 'integer', 'min:1'],
            'pedido' => ['nullable', 'string', 'max:255'],

            'detalles_caja' => ['required', 'array', 'min:1'],
            'detalles_caja.*.caja' => ['required', 'integer', 'min:1', 'distinct'],
            'detalles_caja.*.piezas' => ['required', 'integer', 'gt:0'],
            'detalles_caja.*.tipo_embalaje' => ['required', 'integer', 'in:1,2,3,4,5'],
        ];
    }

    public function messages(): array
    {
        return [
            'referencia_externa.required' => 'La referencia_externa es obligatoria.',
            'numero_cajas.required' => 'El numero_cajas es obligatorio.',
            'numero_cajas.min' => 'El numero_cajas debe ser al menos 1.',
            'detalles_caja.required' => 'El arreglo detalles_caja es obligatorio.',
            'detalles_caja.array' => 'detalles_caja debe ser un arreglo.',
            'detalles_caja.*.caja.required' => 'Cada detalle debe incluir caja.',
            'detalles_caja.*.caja.distinct' => 'No se permiten cajas repetidas en detalles_caja.',
            'detalles_caja.*.piezas.required' => 'Cada detalle debe incluir piezas.',
            'detalles_caja.*.piezas.gt' => 'Las piezas deben ser mayores a 0.',
            'detalles_caja.*.tipo_embalaje.required' => 'Cada detalle debe incluir tipo_embalaje.',
            'detalles_caja.*.tipo_embalaje.in' => 'tipo_embalaje solo permite los valores 1,2,3,4,5.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $numeroCajas = (int) $this->input('numero_cajas', 0);
            $detalles = $this->input('detalles_caja', []);

            if (is_array($detalles) && $numeroCajas > 0 && count($detalles) !== $numeroCajas) {
                $validator->errors()->add(
                    'numero_cajas',
                    'numero_cajas debe coincidir con la cantidad de elementos en detalles_caja.'
                );
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::warning('TEMP DEBUG etiquetas: validacion fallida', [
            'route' => $this->path(),
            'method' => $this->method(),
            'ip' => $this->ip(),
            'user_id' => optional($this->user())->id,
            'errors' => $validator->errors()->toArray(),
            'payload' => $this->all(),
        ]);

        throw new HttpResponseException(response()->json([
            'ok' => false,
            'mensaje' => 'Error de validacion en el payload.',
            'detalle' => 'Revisa el objeto errors para corregir los campos enviados.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
