<?php

namespace App\Http\Controllers;

use App\Services\ExcelOtParser;
use App\Models\Orden;
use App\Models\Solicitud;
use App\Models\ServicioEmpresa;
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

            // Parsear desde el archivo guardado (incluye servicios detectados)
            $parser = new ExcelOtParser();
            $parsed = $parser->parseWithServicios(Storage::path($storedPath));
            $datos = $parsed['datos'] ?? [];
            $serviciosRaw = $parsed['servicios'] ?? [];

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
            if (!empty($datos['descripcion_producto'])) {
                $prefill['descripcion_producto'] = $datos['descripcion_producto'];
            }
            if (!empty($datos['area'])) {
                $prefill['area'] = $datos['area'];
            }
            if (!empty($datos['sku'])) {
                $prefill['sku'] = trim((string) $datos['sku']);
            }
            if (!empty($datos['origen'])) {
                $prefill['origen'] = trim((string) $datos['origen']);
            }
            if (!empty($datos['pedimento'])) {
                $prefill['pedimento'] = trim((string) $datos['pedimento']);
            }

            // Adicional: cantidad (para precargar el formulario)
            if (!empty($datos['cantidad'])) {
                $prefill['cantidad'] = $this->parseCantidad($datos['cantidad']);
            }

            // Resolver servicios contra BD
            $servicios = [];
            $noEncontrados = [];
            foreach ($serviciosRaw as $s) {
                $nombre = trim((string)($s['nombre_servicio'] ?? ''));
                if ($nombre === '') continue;

                $serv = $this->buscarServicioPorNombreOCodigo($nombre);
                if (!$serv) {
                    $noEncontrados[] = $nombre;
                    continue;
                }

                $servicios[] = [
                    'id_servicio' => (int) $serv->id,
                    'nombre_servicio' => (string) $serv->nombre,
                    'cantidad' => $this->parseCantidad($s['cantidad'] ?? $prefill['cantidad'] ?? null),
                    'sku' => $this->parseTexto($s['sku'] ?? $prefill['sku'] ?? null),
                    'origen' => $this->parseTexto($s['origen'] ?? $prefill['origen'] ?? null),
                    'pedimento' => $this->parseTexto($s['pedimento'] ?? $prefill['pedimento'] ?? null),
                    'tipo_tarifa' => (string)($s['tipo_tarifa'] ?? 'NORMAL'),
                    'precio_unitario' => $this->parsePrecio($s['precio_unitario'] ?? null),
                ];
            }

            // Consistencia: si no detectó lista pero sí hay servicio simple, tratarlo como 1 servicio
            if (count($servicios) === 0 && !empty($datos['servicio'])) {
                $nombre = trim((string) $datos['servicio']);
                $nombre = preg_split('/\R+/u', $nombre)[0] ?? $nombre;
                $serv = $this->buscarServicioPorNombreOCodigo($nombre);
                if ($serv) {
                    $servicios[] = [
                        'id_servicio' => (int) $serv->id,
                        'nombre_servicio' => (string) $serv->nombre,
                        'cantidad' => $prefill['cantidad'] ?? null,
                        'sku' => $this->parseTexto($prefill['sku'] ?? null),
                        'origen' => $this->parseTexto($prefill['origen'] ?? null),
                        'pedimento' => $this->parseTexto($prefill['pedimento'] ?? null),
                        'tipo_tarifa' => 'NORMAL',
                        'precio_unitario' => null,
                    ];
                } else {
                    $noEncontrados[] = $nombre;
                }
            }

            if (count($noEncontrados) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron estos servicios en catálogo: ' . implode(', ', array_values(array_unique($noEncontrados))),
                    'servicios_no_encontrados' => array_values(array_unique($noEncontrados)),
                ], 422);
            }

            // Asegurar cantidad por servicio (fallback a cantidad global)
            foreach ($servicios as $i => $s) {
                if (empty($s['cantidad']) && !empty($prefill['cantidad'])) {
                    $servicios[$i]['cantidad'] = $prefill['cantidad'];
                }
            }

            $isMulti = count($servicios) >= 2;

            return response()->json([
                'success' => true,
                'archivo' => [
                    'nombre' => $originalName,
                    'stored_name' => $storedName,
                    'ruta' => route('solicitudes.excel.download', ['archivo' => $storedName], false),
                ],
                'prefill' => $prefill,
                'servicios' => $servicios,
                'is_multi' => $isMulti,
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

    private function parsePrecio($value): ?float
    {
        if ($value === null) return null;
        if (is_int($value) || is_float($value)) return (float) $value;

        $raw = trim((string) $value);
        if ($raw === '') return null;
        $raw = preg_replace('/[^0-9.,-]+/', '', $raw);
        if ($raw === '' || $raw === '-') return null;

        if (substr_count($raw, ',') === 1 && substr_count($raw, '.') === 0) {
            $raw = str_replace(',', '.', $raw);
        } else {
            $raw = str_replace(',', '', $raw);
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    private function parseTexto($value): ?string
    {
        if ($value === null) return null;
        $text = trim((string) $value);
        return $text !== '' ? $text : null;
    }

    private function buscarServicioPorNombreOCodigo(string $texto): ?ServicioEmpresa
    {
        $texto = trim($texto);
        if ($texto === '') return null;

        // Intentar por código si parece tenerlo
        $codigo = null;
        if (preg_match('/([A-Z]+)\s*-?\s*(\d+)/i', $texto, $m)) {
            $codigo = strtoupper($m[1]) . $m[2];
        }

        return ServicioEmpresa::where(function ($q) use ($texto, $codigo) {
            if ($codigo) {
                $q->where('codigo', 'LIKE', '%' . $codigo . '%');
            }
            $q->orWhere('nombre', 'LIKE', '%' . $texto . '%');
        })->first();
    }

    public function download(string $archivo)
    {
        // Evitar path traversal
        $archivo = basename($archivo);
        $path = 'solicitudes_excel/' . $archivo;

        abort_unless(Storage::exists($path), 404);

        return Storage::download($path, $archivo);
    }

    public function downloadBySolicitud(Solicitud $solicitud)
    {
        $this->authorize('view', $solicitud);

        $stored = basename((string)($solicitud->archivo_excel_stored_name ?? ''));
        if ($stored === '') {
            abort(404, 'No hay archivo Excel asociado a esta solicitud');
        }

        $path = 'solicitudes_excel/' . $stored;
        abort_unless(Storage::exists($path), 404);

        $downloadName = $solicitud->archivo_excel_nombre_original ?: $stored;
        $downloadName = basename($downloadName);

        return Storage::download($path, $downloadName);
    }

    public function downloadOrigenFromOrden(Orden $orden)
    {
        $this->authorize('view', $orden);

        $orden->loadMissing('solicitud');
        $solicitud = $orden->solicitud;
        if (!$solicitud) {
            abort(404, 'La OT no tiene solicitud asociada');
        }

        // Reusar el método por solicitud (incluye authorize view solicitud)
        return $this->downloadBySolicitud($solicitud);
    }
}
