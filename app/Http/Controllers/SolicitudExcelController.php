<?php

namespace App\Http\Controllers;

use App\Services\ExcelOtParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SolicitudExcelController extends Controller
{
    public function parseExcel(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $file = $request->file('archivo');

            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension() ?: 'xlsx';
            $storedName = now()->format('Ymd_His') . '_' . Str::random(16) . '.' . $extension;

            // Guardar en storage/app/solicitudes_excel/
            $storedPath = $file->storeAs('solicitudes_excel', $storedName);

            // Parsear desde el archivo guardado
            $parser = new ExcelOtParser();
            $datos = $parser->parse(Storage::path($storedPath));

            // Extraer SOLO campos solicitados (si existen)
            $prefill = [];

            if (!empty($datos['centro'])) {
                $prefill['centro_trabajo'] = $datos['centro'];
            }
            if (!empty($datos['centro_costos'])) {
                $prefill['centro_costos'] = $datos['centro_costos'];
            }
            if (!empty($datos['marca'])) {
                $prefill['marca'] = $datos['marca'];
            }
            if (!empty($datos['servicio'])) {
                $prefill['tipo_servicio'] = $datos['servicio'];
            }
            if (!empty($datos['descripcion_producto'])) {
                $prefill['descripcion_producto'] = $datos['descripcion_producto'];
            }
            if (!empty($datos['area'])) {
                $prefill['area'] = $datos['area'];
            }

            // Adicional: cantidad (para precargar el formulario)
            if (!empty($datos['cantidad'])) {
                $prefill['cantidad'] = $this->parseCantidad($datos['cantidad']);
            }

            return response()->json([
                'success' => true,
                'archivo' => [
                    'nombre' => $originalName,
                    'ruta' => route('solicitudes.excel.download', ['archivo' => $storedName], false),
                ],
                'prefill' => $prefill,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al parsear Excel de solicitud (web): ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function parseCantidad($value)
    {
        if (is_int($value) || is_float($value)) return $value;

        $raw = trim((string) $value);
        if ($raw === '') return null;

        // Mantener dígitos, coma y punto
        $raw = preg_replace('/[^0-9.,-]+/', '', $raw);
        if ($raw === '' || $raw === '-' ) return null;

        // Normalizar coma decimal
        if (substr_count($raw, ',') === 1 && substr_count($raw, '.') === 0) {
            $raw = str_replace(',', '.', $raw);
        } else {
            // si hay miles con coma, quitar comas
            $raw = str_replace(',', '', $raw);
        }

        $num = is_numeric($raw) ? (float) $raw : null;
        if ($num === null) return null;

        // Si es entero, devolver int
        if (abs($num - (int) $num) < 0.00001) return (int) $num;
        return $num;
    }

    public function download(string $archivo)
    {
        // Evitar path traversal
        $archivo = basename($archivo);
        $path = 'solicitudes_excel/' . $archivo;

        abort_unless(Storage::exists($path), 404);

        return Storage::download($path, $archivo);
    }
}
