<?php

namespace App\Http\Controllers;

use App\Services\ExcelOtParser;
use App\Models\Orden;
use App\Models\Solicitud;
use App\Models\ServicioEmpresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SolicitudExcelController extends Controller
{
    public function parseExcel(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:10240',
            'modo' => 'nullable|string|in:normal,productos',
        ]);

        try {
            $file = $request->file('archivo');

            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension() ?: 'xlsx';
            $storedName = now()->format('Ymd_His') . '_' . Str::random(16) . '.' . $extension;

            // Guardar en storage/app/solicitudes_excel/
            $storedPath = $file->storeAs('solicitudes_excel', $storedName);

            // Parsear desde el archivo guardado (incluye servicios detectados)
            $modo = (string) $request->input('modo', 'normal');
            $isProductosMode = $modo === 'productos';

            $puedeProductos = false;
            if (Schema::hasTable('permissions') && $request->user() && method_exists($request->user(), 'hasPermissionTo')) {
                $puedeProductos = $request->user()->hasPermissionTo('subir_excel_productos');
            }

            if ($isProductosMode && !$puedeProductos) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para usar la carga masiva de solicitudes por Excel.',
                ], 403);
            }

            $datos = [];
            $serviciosRaw = [];
            $erroresImportacion = [];
            $warnings = [];

            if ($isProductosMode) {
                $parsedProductos = $this->parseProductosTemplate(Storage::path($storedPath));
                $datos = $parsedProductos['datos'] ?? [];
                $serviciosRaw = $parsedProductos['servicios'] ?? [];
                $erroresImportacion = $parsedProductos['errores'] ?? [];
                $warnings = $parsedProductos['warnings'] ?? [];
            } else {
                $parser = new ExcelOtParser();
                $parsed = $parser->parseWithServicios(Storage::path($storedPath));
                $datos = $parsed['datos'] ?? [];
                $serviciosRaw = $parsed['servicios'] ?? [];
            }

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
            if (!empty($datos['pedido'])) {
                $prefill['pedido'] = trim((string) $datos['pedido']);
            }
            if (!empty($datos['referencia_externa'])) {
                $prefill['referencia_externa'] = trim((string) $datos['referencia_externa']);
            }
            if (!empty($datos['notas'])) {
                $prefill['notas'] = trim((string) $datos['notas']);
            }

            // Adicional: cantidad (para precargar el formulario)
            if (!empty($datos['cantidad'])) {
                $prefill['cantidad'] = $this->parseCantidad($datos['cantidad']);
            }

            // Resolver servicios contra BD
            $servicios = [];
            $conServicioAsignado = 0;
            $pendienteAsignacion = 0;
            $conServicioAutomatico = 0;
            // Centro del cliente que sube el archivo: se usa para consultar el catálogo SKU->Servicio del coordinador
            $centroIdCatalogoSku = (int) ($request->user()?->centro_trabajo_id ?? 0);
            foreach ($serviciosRaw as $s) {
                $filaExcel = isset($s['row_number']) ? (int) $s['row_number'] : null;
                $nombre = $this->parseTexto($s['nombre_servicio'] ?? null);
                $skuFila = $this->parseTexto($s['sku'] ?? $prefill['sku'] ?? null);
                $origenFila = $this->parseTexto($s['origen'] ?? $prefill['origen'] ?? null);
                $pedimentoFila = $this->parseTexto($s['pedimento'] ?? $prefill['pedimento'] ?? null);
                $cantidadFila = $this->parseCantidad($s['cantidad'] ?? $prefill['cantidad'] ?? null);
                $tipoTarifaFila = (string)($s['tipo_tarifa'] ?? 'NORMAL');
                $precioUnitarioFila = $this->parsePrecio($s['precio_unitario'] ?? null);

                // Regla: Tipo de Servicio vacío + SKU vacío => fila inválida.
                if ($nombre === null) {
                    if ($skuFila === null) {
                        $erroresImportacion[] = [
                            'fila' => $filaExcel,
                            'motivo' => 'Debe existir al menos un servicio o un SKU para identificar la fila.',
                        ];
                        continue;
                    }

                    // Catálogo del coordinador: si este SKU ya tiene un servicio asignado, autoasignarlo.
                    $idServicioAuto = $centroIdCatalogoSku
                        ? \App\Models\SkuServicio::resolverServicioId($centroIdCatalogoSku, $skuFila)
                        : null;
                    $servAuto = $idServicioAuto ? ServicioEmpresa::find($idServicioAuto) : null;

                    if ($servAuto) {
                        $servicios[] = [
                            'id_servicio' => (int) $servAuto->id,
                            'nombre_servicio' => (string) $servAuto->nombre,
                            'service_assignment_status' => 'assigned',
                            'cantidad' => $cantidadFila,
                            'sku' => $skuFila,
                            'origen' => $origenFila,
                            'pedimento' => $pedimentoFila,
                            'tipo_tarifa' => $tipoTarifaFila,
                            'precio_unitario' => $precioUnitarioFila,
                            'descripcion' => $this->parseTexto($s['descripcion'] ?? null),
                            'po'    => $this->parseTexto($s['po'] ?? null),
                            'vpn'   => $this->parseTexto($s['vpn'] ?? null),
                            'marca' => $this->parseTexto($s['marca'] ?? null),
                            'notas' => $this->parseTexto($s['notas'] ?? null),
                        ];
                        $conServicioAsignado++;
                        $conServicioAutomatico++;
                        continue;
                    }

                    $servicios[] = [
                        'id_servicio' => null,
                        'nombre_servicio' => 'Pendiente de asignación',
                        'service_assignment_status' => 'pending',
                        'cantidad' => $cantidadFila,
                        'sku' => $skuFila,
                        'origen' => $origenFila,
                        'pedimento' => $pedimentoFila,
                        'tipo_tarifa' => $tipoTarifaFila,
                        'precio_unitario' => $precioUnitarioFila,
                        'descripcion' => $this->parseTexto($s['descripcion'] ?? null),
                        // Campos específicos del template de productos
                        'po'    => $this->parseTexto($s['po'] ?? null),
                        'vpn'   => $this->parseTexto($s['vpn'] ?? null),
                        'marca' => $this->parseTexto($s['marca'] ?? null),
                        'notas' => $this->parseTexto($s['notas'] ?? null),
                    ];
                    $pendienteAsignacion++;
                    continue;
                }

                $serv = $this->buscarServicioPorNombreOCodigo($nombre);
                if (!$serv) {
                    $erroresImportacion[] = [
                        'fila' => $filaExcel,
                        'motivo' => "No se encontró el servicio en catálogo: {$nombre}",
                    ];
                    continue;
                }

                $servicios[] = [
                    'id_servicio' => (int) $serv->id,
                    'nombre_servicio' => (string) $serv->nombre,
                    'service_assignment_status' => 'assigned',
                    'cantidad' => $cantidadFila,
                    'sku' => $skuFila,
                    'origen' => $origenFila,
                    'pedimento' => $pedimentoFila,
                    'tipo_tarifa' => $tipoTarifaFila,
                    'precio_unitario' => $precioUnitarioFila,
                    'descripcion' => $this->parseTexto($s['descripcion'] ?? null),
                ];
                $conServicioAsignado++;
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
                        'service_assignment_status' => 'assigned',
                        'cantidad' => $prefill['cantidad'] ?? null,
                        'sku' => $this->parseTexto($prefill['sku'] ?? null),
                        'origen' => $this->parseTexto($prefill['origen'] ?? null),
                        'pedimento' => $this->parseTexto($prefill['pedimento'] ?? null),
                        'tipo_tarifa' => 'NORMAL',
                        'precio_unitario' => null,
                        'descripcion' => null,
                    ];
                    $conServicioAsignado++;
                } else {
                    $erroresImportacion[] = [
                        'fila' => null,
                        'motivo' => "No se encontró el servicio en catálogo: {$nombre}",
                    ];
                }
            }

            // Consistencia adicional: si no hubo servicio, pero sí SKU global, permitir un pendiente.
            if (count($servicios) === 0 && empty($datos['servicio'])) {
                $skuGlobal = $this->parseTexto($prefill['sku'] ?? null);
                if ($skuGlobal !== null) {
                    $servicios[] = [
                        'id_servicio' => null,
                        'nombre_servicio' => 'Pendiente de asignación',
                        'service_assignment_status' => 'pending',
                        'cantidad' => $prefill['cantidad'] ?? null,
                        'sku' => $skuGlobal,
                        'origen' => $this->parseTexto($prefill['origen'] ?? null),
                        'pedimento' => $this->parseTexto($prefill['pedimento'] ?? null),
                        'tipo_tarifa' => 'NORMAL',
                        'precio_unitario' => null,
                        'descripcion' => null,
                    ];
                    $pendienteAsignacion++;
                }
            }

            $fallidas = count($erroresImportacion);
            $totalProcesadas = $conServicioAsignado + $pendienteAsignacion + $fallidas;
            $resumenImportacion = [
                'con_servicio_asignado' => $conServicioAsignado,
                'con_servicio_automatico' => $conServicioAutomatico,
                'pendiente_asignacion' => $pendienteAsignacion,
                'fallidas' => $fallidas,
                'total_procesadas' => $totalProcesadas,
            ];

            if (count($servicios) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo importar ninguna fila válida. Verifica el archivo e intenta nuevamente.',
                    'resumen_importacion' => $resumenImportacion,
                    'errores_importacion' => $erroresImportacion,
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
                'resumen_importacion' => $resumenImportacion,
                'errores_importacion' => $erroresImportacion,
                'warnings' => array_values(array_filter(array_merge(
                    $warnings,
                    $conServicioAutomatico > 0 ? [
                        "Servicio asignado automáticamente por SKU en {$conServicioAutomatico} fila(s), según el catálogo del coordinador.",
                    ] : [],
                    $fallidas > 0 ? [
                        "Filas con servicio asignado: {$conServicioAsignado}",
                        "Filas pendientes de asignación: {$pendienteAsignacion}",
                        "Filas fallidas: {$fallidas}",
                    ] : []
                ))),
            ]);
        } catch (\Exception $e) {
            if (str_contains((string) $e->getMessage(), 'Faltan columnas obligatorias')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errores_importacion' => [
                        ['fila' => null, 'motivo' => $e->getMessage()],
                    ],
                ], 422);
            }

            Log::error('Error al parsear Excel de solicitud (web): ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function parseProductosTemplate(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $requiredColumns = [
            'po',
            'sku',
            'vpn',
            'marca',
            'qty',
            'pedimento',
            'notas',
        ];

        [$headerRow, $headerMap] = $this->findProductosHeader($sheet);
        $missing = array_values(array_filter($requiredColumns, fn ($key) => !isset($headerMap[$key])));

        if (!empty($missing)) {
            throw new \RuntimeException(
                'Faltan columnas obligatorias en el Excel: ' . implode(', ', $missing) . '.'
            );
        }

        $highestRow = min($sheet->getHighestRow(), 5000);
        $servicios = [];
        $errores = [];
        $emptyStreak = 0;
        $firstMarca = null;
        $firstPo = null;
        $firstVpn = null;

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $po = $this->cellText($sheet, $headerMap['po'], $row);
            $sku = $this->cellText($sheet, $headerMap['sku'], $row);
            $vpn = $this->cellText($sheet, $headerMap['vpn'], $row);
            $marca = $this->cellText($sheet, $headerMap['marca'], $row);
            $qtyRaw = $this->cellRaw($sheet, $headerMap['qty'], $row);
            $pedimento = $this->cellText($sheet, $headerMap['pedimento'], $row);
            $notas = $this->cellText($sheet, $headerMap['notas'], $row);

            $isEmpty = $po === '' && $sku === '' && $vpn === '' && $marca === '' && $pedimento === '' && $notas === '' && trim((string) $qtyRaw) === '';
            if ($isEmpty) {
                $emptyStreak++;
                if ($emptyStreak >= 5) {
                    break;
                }
                continue;
            }
            $emptyStreak = 0;

            if ($sku === '') {
                $errores[] = ['fila' => $row, 'motivo' => 'SKU es obligatorio.'];
                continue;
            }

            $qty = $this->parseCantidad($qtyRaw);
            if ($qty === null || !is_numeric($qty)) {
                $errores[] = ['fila' => $row, 'motivo' => 'QTY debe ser numérico.'];
                continue;
            }

            if ($firstMarca === null && $marca !== '') {
                $firstMarca = $marca;
            }
            if ($firstPo === null && $po !== '') {
                $firstPo = $po;
            }
            if ($firstVpn === null && $vpn !== '') {
                $firstVpn = $vpn;
            }

            $descripcionPartes = array_filter([
                $po !== '' ? "PO: {$po}" : null,
                $vpn !== '' ? "VPN: {$vpn}" : null,
                $marca !== '' ? "Marca: {$marca}" : null,
                $notas !== '' ? "Notas: {$notas}" : null,
            ]);

            $servicios[] = [
                'row_number' => $row,
                'nombre_servicio' => null,
                'cantidad' => $qty,
                'sku' => $sku,
                'origen' => null,
                'pedimento' => $pedimento !== '' ? $pedimento : null,
                'tipo_tarifa' => 'NORMAL',
                'precio_unitario' => null,
                'descripcion' => !empty($descripcionPartes) ? implode(' | ', $descripcionPartes) : null,
                // Campos individuales para vista previa en frontend
                'po' => $po !== '' ? $po : null,
                'vpn' => $vpn !== '' ? $vpn : null,
                'marca' => $marca !== '' ? $marca : null,
                'notas' => $notas !== '' ? $notas : null,
            ];
        }

        $datos = [
            'marca' => $firstMarca,
            'pedido' => $firstPo,
            'referencia_externa' => $firstVpn,
        ];

        return [
            'datos' => array_filter($datos, fn ($v) => $v !== null && $v !== ''),
            'servicios' => $servicios,
            'errores' => $errores,
            'warnings' => [
                'Se cargó plantilla de productos. Asigna servicio a cada fila antes de crear la solicitud.',
            ],
        ];
    }

    private function findProductosHeader(Worksheet $sheet): array
    {
        $highestRow = min($sheet->getHighestRow(), 50);
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $aliases = [
            'po' => ['po', 'pedido', 'orden de compra', 'purchase order'],
            'sku' => ['sku'],
            'vpn' => ['vpn', 'numero de parte', 'número de parte', 'np', 'n/p', 'referencia externa'],
            'marca' => ['marca'],
            'qty' => ['qty', 'qty(pz', 'qty (pz', 'cantidad', 'pzs', 'piezas'],
            'pedimento' => ['pedimento', 'numero de pedimento', 'número de pedimento'],
            'notas' => ['notas', 'comentarios', 'observaciones', 'observación'],
        ];

        $bestRow = 1;
        $bestMap = [];
        $bestCount = -1;

        for ($row = 1; $row <= $highestRow; $row++) {
            $map = [];
            for ($col = 1; $col <= min($highestColumnIndex, 60); $col++) {
                $raw = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                if ($raw === null || trim((string) $raw) === '') {
                    continue;
                }
                $norm = $this->normalizarEtiqueta((string) $raw);
                foreach ($aliases as $key => $labels) {
                    foreach ($labels as $label) {
                        if (str_contains($norm, $this->normalizarEtiqueta($label))) {
                            if (!isset($map[$key])) {
                                $map[$key] = $col;
                            }
                            break;
                        }
                    }
                }
            }

            if (count($map) > $bestCount) {
                $bestCount = count($map);
                $bestMap = $map;
                $bestRow = $row;
            }
        }

        return [$bestRow, $bestMap];
    }

    private function normalizarEtiqueta(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', '(', ')', '.', ','],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u', ' ', ' ', ' ', ' '],
            $value
        );
        return preg_replace('/\s+/', ' ', $value) ?? '';
    }

    private function cellText(Worksheet $sheet, int $col, int $row): string
    {
        $v = $sheet->getCellByColumnAndRow($col, $row)->getValue();
        return trim((string) $v);
    }

    private function cellRaw(Worksheet $sheet, int $col, int $row)
    {
        return $sheet->getCellByColumnAndRow($col, $row)->getValue();
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
