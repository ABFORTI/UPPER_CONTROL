<?php

namespace App\Http\Requests\Api\Integraciones;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class StoreEtiquetasCvaGdlSolicitudRequest extends FormRequest
{
    private const TIPO_PEDIDO_CAJA = 'caja';
    private const TIPO_PEDIDO_TARIMA = 'tarima';

    public function authorize(): bool
    {
        // La autenticacion se controla por middleware auth:sanctum.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tipoPedidoInput = $this->input('tipo_pedido');
        $tipoPedido = self::TIPO_PEDIDO_CAJA;

        if (is_string($tipoPedidoInput) && trim($tipoPedidoInput) !== '') {
            $tipoPedido = strtolower(trim($tipoPedidoInput));
        }

        $this->merge([
            'tipo_pedido' => $tipoPedido,
        ]);
    }

    public function rules(): array
    {
        return [
            'referencia_externa' => ['required', 'string', 'max:255'],
            'tipo_pedido' => ['required', 'string', 'in:caja,tarima'],
            'origen' => ['nullable', 'string', 'max:100'],
            'sede' => ['nullable', 'string', 'max:20'],
            'paqueteria' => ['nullable', 'string', 'max:255'],
            'numero_factura' => ['nullable', 'string', 'max:255'],
            'numero_cajas' => ['nullable', 'integer', 'min:1'],
            'numero_tarimas' => ['nullable', 'integer', 'min:1'],
            'pedido' => ['nullable', 'string', 'max:255'],

            'detalles_caja' => ['nullable', 'array', 'min:1'],
            'detalles_caja.*.caja' => ['required_with:detalles_caja', 'integer', 'min:1', 'distinct'],
            'detalles_caja.*.piezas' => ['required_with:detalles_caja', 'integer', 'gt:0'],
            'detalles_caja.*.tipo_embalaje' => ['required_with:detalles_caja'],

            'detalles_tarima' => ['nullable', 'array', 'min:1'],
            'detalles_tarima.*.tarima' => ['required_with:detalles_tarima', 'integer', 'min:1', 'distinct'],
            'detalles_tarima.*.piezas' => ['required_with:detalles_tarima', 'integer', 'gt:0'],
            'detalles_tarima.*.tipo_embalaje' => ['required_with:detalles_tarima'],
        ];
    }

    public function messages(): array
    {
        return [
            'referencia_externa.required' => 'La referencia_externa es obligatoria.',
            'tipo_pedido.required' => 'El tipo_pedido es obligatorio.',
            'tipo_pedido.in' => 'tipo_pedido solo permite los valores caja o tarima.',

            'numero_cajas.min' => 'El numero_cajas debe ser al menos 1.',
            'numero_tarimas.min' => 'El numero_tarimas debe ser al menos 1.',

            'detalles_caja.required' => 'El arreglo detalles_caja es obligatorio.',
            'detalles_caja.array' => 'detalles_caja debe ser un arreglo.',
            'detalles_caja.*.caja.required' => 'Cada detalle debe incluir caja.',
            'detalles_caja.*.caja.distinct' => 'No se permiten cajas repetidas en detalles_caja.',
            'detalles_caja.*.piezas.required' => 'Cada detalle debe incluir piezas.',
            'detalles_caja.*.piezas.gt' => 'Las piezas deben ser mayores a 0.',
            'detalles_caja.*.tipo_embalaje.required' => 'Cada detalle debe incluir tipo_embalaje.',

            'detalles_tarima.required' => 'El arreglo detalles_tarima es obligatorio.',
            'detalles_tarima.array' => 'detalles_tarima debe ser un arreglo.',
            'detalles_tarima.*.tarima.required' => 'Cada detalle debe incluir tarima.',
            'detalles_tarima.*.tarima.distinct' => 'No se permiten tarimas repetidas en detalles_tarima.',
            'detalles_tarima.*.piezas.required' => 'Cada detalle debe incluir piezas.',
            'detalles_tarima.*.piezas.gt' => 'Las piezas deben ser mayores a 0.',
            'detalles_tarima.*.tipo_embalaje.required' => 'Cada detalle debe incluir tipo_embalaje.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tipoPedido = $this->input('tipo_pedido', self::TIPO_PEDIDO_CAJA);

            if ($tipoPedido === self::TIPO_PEDIDO_TARIMA) {
                $this->validateTarimaPayload($validator);
                return;
            }

            $this->validateCajaPayload($validator);
        });
    }

    private function validateCajaPayload($validator): void
    {
        $numeroCajas = (int) $this->input('numero_cajas', 0);
        $detalles = $this->input('detalles_caja', []);

        if ($numeroCajas <= 0) {
            $validator->errors()->add('numero_cajas', 'El numero_cajas es obligatorio para tipo_pedido caja.');
        }

        if (!is_array($detalles) || count($detalles) === 0) {
            $validator->errors()->add('detalles_caja', 'El arreglo detalles_caja es obligatorio para tipo_pedido caja.');
            return;
        }

        if ($numeroCajas > 0 && count($detalles) !== $numeroCajas) {
            $validator->errors()->add(
                'numero_cajas',
                'numero_cajas debe coincidir con la cantidad de elementos en detalles_caja.'
            );
        }

        foreach ($detalles as $index => $detalle) {
            $tipoEmbalaje = is_array($detalle) ? ($detalle['tipo_embalaje'] ?? null) : null;
            if (!$this->isCajaTipoEmbalajeValido($tipoEmbalaje)) {
                $validator->errors()->add(
                    'detalles_caja.' . $index . '.tipo_embalaje',
                    'tipo_embalaje para caja solo permite 1,2,3,4,5 o "Embalaje de Pantalla".'
                );
            }
        }
    }

    private function validateTarimaPayload($validator): void
    {
        $numeroTarimas = (int) $this->input('numero_tarimas', 0);
        $detalles = $this->input('detalles_tarima', []);

        if ($numeroTarimas <= 0) {
            $validator->errors()->add('numero_tarimas', 'El numero_tarimas es obligatorio para tipo_pedido tarima.');
        }

        if (!is_array($detalles) || count($detalles) === 0) {
            $validator->errors()->add('detalles_tarima', 'El arreglo detalles_tarima es obligatorio para tipo_pedido tarima.');
            return;
        }

        if ($numeroTarimas > 0 && count($detalles) !== $numeroTarimas) {
            $validator->errors()->add(
                'numero_tarimas',
                'numero_tarimas debe coincidir con la cantidad de elementos en detalles_tarima.'
            );
        }

        foreach ($detalles as $index => $detalle) {
            $tipoEmbalaje = is_array($detalle) ? ($detalle['tipo_embalaje'] ?? null) : null;
            if (!$this->isTarimaTipoEmbalajeValido($tipoEmbalaje)) {
                $validator->errors()->add(
                    'detalles_tarima.' . $index . '.tipo_embalaje',
                    'tipo_embalaje para tarima solo permite 1,2,3 o los servicios de emplayado configurados.'
                );
            }
        }
    }

    private function isCajaTipoEmbalajeValido($tipoEmbalaje): bool
    {
        $numero = $this->toIntegerLike($tipoEmbalaje);
        if ($numero !== null && in_array($numero, [1, 2, 3, 4, 5], true)) {
            return true;
        }

        if (!is_string($tipoEmbalaje)) {
            return false;
        }

        return $this->normalizeText($tipoEmbalaje) === 'embalaje de pantalla';
    }

    private function isTarimaTipoEmbalajeValido($tipoEmbalaje): bool
    {
        $numero = $this->toIntegerLike($tipoEmbalaje);
        if ($numero !== null && in_array($numero, [1, 2, 3], true)) {
            return true;
        }

        if (!is_string($tipoEmbalaje)) {
            return false;
        }

        $normalized = $this->normalizeText($tipoEmbalaje);

        return in_array($normalized, [
            'emplayado de tarima',
            'emplayado de tarima con esquinero',
            'emplayado y flejado de tarima',
        ], true);
    }

    private function toIntegerLike($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return null;
    }

    private function normalizeText(string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value));
        return strtolower((string) $normalized);
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
