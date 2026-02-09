<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Str;

/**
 * Servicio para parsear archivos Excel de Órdenes de Trabajo
 * Lee el archivo y extrae información relevante para precargar el formulario
 */
class ExcelOtParser
{
    /**
     * Parsear archivo Excel y extraer datos
     * 
     * @param string $filePath Ruta absoluta al archivo Excel
     * @return array Datos extraídos del Excel
     * @throws \Exception Si el archivo no puede ser leído o tiene formato inválido
     */
    public function parse(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            
            // Extraer datos buscando etiquetas conocidas
            $datos = $this->extraerDatos($sheet);
            
            return $datos;
            
        } catch (\Exception $e) {
            throw new \Exception("Error al leer el archivo Excel: " . $e->getMessage());
        }
    }

    /**
     * Variante que además detecta una lista de servicios.
     *
     * @return array{datos: array, servicios: array<int, array{nombre_servicio: string, cantidad: mixed, tipo_tarifa: string, precio_unitario: mixed}>}
     */
    public function parseWithServicios(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            $datos = $this->extraerDatos($sheet);
            $servicios = $this->extraerServicios($sheet, $datos);

            return [
                'datos' => $datos,
                'servicios' => $servicios,
            ];
        } catch (\Exception $e) {
            throw new \Exception("Error al leer el archivo Excel: " . $e->getMessage());
        }
    }
    
    /**
     * Extraer datos del Excel buscando etiquetas/encabezados conocidos
     * 
     * @param Worksheet $sheet
     * @return array
     */
    private function extraerDatos(Worksheet $sheet): array
    {
        $datos = [];
        $highestRow = min($sheet->getHighestRow(), 100); // Limitar búsqueda a 100 filas
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        // Mapeo de etiquetas a campos (ordenados de más específico a menos específico)
        $etiquetasMap = [
            'centro_costos' => ['centro de costos', 'centro_costos', 'centro costos', 'codigo', 'código'],
            'centro' => ['centro de trabajo', 'centro_trabajo', 'centro operativo', 'centro'],
            'descripcion_producto' => ['producto', 'descripción del producto', 'descripcion del producto', 'descripción producto'],
            'servicio' => ['servicio', 'tipo de servicio', 'tipo servicio'],
            'marca' => ['marca'],
            'area' => ['area', 'área'],
            'solicitante' => ['solicitante', 'cliente'],
            'cantidad' => ['cantidad', 'pzs', 'piezas'],
            'upc' => ['upc', 'codigo barras', 'código de barras'],
        ];
        
        // Buscar etiquetas en las primeras filas
        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= min($highestColumnIndex, 15); $col++) {
                $cellValue = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                
                if (empty($cellValue)) continue;
                
                $cellValueNormalized = $this->normalizar($cellValue);
                
                // Buscar coincidencias con etiquetas conocidas
                foreach ($etiquetasMap as $campo => $etiquetas) {
                    foreach ($etiquetas as $etiqueta) {
                        if (Str::contains($cellValueNormalized, $this->normalizar($etiqueta))) {
                            // A veces el Excel trae "Etiqueta: Valor" en la misma celda
                            $valorInline = $this->extraerValorInline($cellValue);

                            // Valor generalmente está en la celda de la derecha o debajo
                            $valorDerecha = $sheet->getCellByColumnAndRow($col + 1, $row)->getValue();
                            $valorAbajo = $this->buscarPrimerValorAbajo($sheet, $col, $row, 10);
                            
                            // Si el valor de la derecha parece ser otro encabezado, usar el de abajo
                            $valorDerechaEsEncabezado = false;
                            if (!empty($valorDerecha)) {
                                $valorDerechaNormalizado = $this->normalizar($valorDerecha);
                                foreach ($etiquetasMap as $otrosCampos => $otrasEtiquetas) {
                                    foreach ($otrasEtiquetas as $otraEtiqueta) {
                                        if (Str::contains($valorDerechaNormalizado, $this->normalizar($otraEtiqueta))) {
                                            $valorDerechaEsEncabezado = true;
                                            break 2;
                                        }
                                    }
                                }
                            }
                            
                            $valor = (!empty($valorDerecha) && !$valorDerechaEsEncabezado) ? $valorDerecha : $valorAbajo;
                            
                            if (!isset($datos[$campo])) {
                                $final = null;

                                if (!empty($valorInline)) {
                                    $final = $valorInline;
                                } elseif (!empty($valor)) {
                                    $final = is_string($valor) ? trim($valor) : trim((string) $valor);
                                }

                                if (!empty($final)) {
                                    $datos[$campo] = $final;
                                }
                            }
                            break 2; // Salir de ambos loops
                        }
                    }
                }
            }
        }
        
        return $datos;
    }

    /**
     * Extrae una lista de servicios desde una tabla horizontal (encabezados) o desde el campo 'servicio'.
     */
    private function extraerServicios(Worksheet $sheet, array $datos): array
    {
        // Caso especial: Excel de facturación (columnas: Cliente, Cantidad, Precio, DATOS DE LA ORDEN DE TRABAJO, ...)
        // Agrupa por OT_ID extraído de "... OT: {id}" y construye servicios.
        $fromFacturacion = $this->extraerServiciosDesdeTablaFacturacionExport($sheet);
        if (count($fromFacturacion) > 0) {
            $otIds = array_keys($fromFacturacion);
            if (count($otIds) === 1) {
                return $fromFacturacion[$otIds[0]];
            }
            return [];
        }

        // Caso especial: si el Excel es el export generado por OT index (filas OT x Servicio)
        // detectamos encabezado con 'folio' y 'servicio' y agrupamos por folio.
        $fromOtIndex = $this->extraerServiciosDesdeTablaOtIndex($sheet);
        if (count($fromOtIndex) > 0) {
            // Si todas las filas pertenecen al mismo folio/OT, devolver la lista de servicios
            $folios = array_keys($fromOtIndex);
            if (count($folios) === 1) {
                return $fromOtIndex[$folios[0]];
            }
            // Si hay múltiples folios, no intentamos construir una única solicitud automáticamente
            return [];
        }

        // 1) Intentar por tabla (encabezados tipo: "Tipo de Servicio", "Cantidad", "Precio Unitario")
        $fromTable = $this->extraerServiciosDesdeTabla($sheet);
        if (count($fromTable) > 0) {
            return $fromTable;
        }

        // 2) Fallback: usar el dato simple detectado (puede traer múltiples líneas)
        $raw = $datos['servicio'] ?? null;
        if (empty($raw)) return [];

        $qty = $datos['cantidad'] ?? null;
        $names = $this->splitServicios($raw);

        return array_values(array_filter(array_map(function ($name) use ($qty) {
            $name = trim((string) $name);
            if ($name === '') return null;
            return [
                'nombre_servicio' => $name,
                'cantidad' => $qty,
                'tipo_tarifa' => 'NORMAL',
                'precio_unitario' => null,
            ];
        }, $names)));
    }

    private function extraerServiciosDesdeTabla(Worksheet $sheet): array
    {
        $highestRow = min($sheet->getHighestRow(), 200);
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $serviceCol = null;
        $qtyCol = null;
        $priceCol = null;
        $tarifaCol = null;
        $headerRow = null;

        // Buscar encabezados en primeras filas
        for ($row = 1; $row <= min($highestRow, 30); $row++) {
            $foundService = null;
            $foundQty = null;
            $foundPrice = null;
            $foundTarifa = null;

            for ($col = 1; $col <= min($highestColumnIndex, 40); $col++) {
                $v = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                if ($v === null || trim((string) $v) === '') continue;
                $n = $this->normalizar($v);

                if ($foundService === null && (Str::contains($n, 'tipo de servicio') || $n === 'servicio' || Str::contains($n, 'servicio'))) {
                    $foundService = $col;
                }
                if ($foundQty === null && (Str::contains($n, 'cantidad') || $n === 'pzs' || Str::contains($n, 'piezas'))) {
                    $foundQty = $col;
                }
                if ($foundPrice === null && (Str::contains($n, 'precio unitario') || Str::contains($n, 'precio'))) {
                    $foundPrice = $col;
                }
                if ($foundTarifa === null && (Str::contains($n, 'tarifa') || Str::contains($n, 'tipo tarifa'))) {
                    $foundTarifa = $col;
                }
            }

            if ($foundService !== null) {
                $headerRow = $row;
                $serviceCol = $foundService;
                $qtyCol = $foundQty;
                $priceCol = $foundPrice;
                $tarifaCol = $foundTarifa;
                break;
            }
        }

        if ($headerRow === null || $serviceCol === null) {
            return [];
        }

        $servicios = [];
        $emptyStreak = 0;
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $sv = $sheet->getCellByColumnAndRow($serviceCol, $row)->getValue();
            $svStr = trim((string) $sv);
            if ($svStr === '') {
                $emptyStreak++;
                if ($emptyStreak >= 5) break;
                continue;
            }
            $emptyStreak = 0;

            $qv = $qtyCol ? $sheet->getCellByColumnAndRow($qtyCol, $row)->getValue() : null;
            $pv = $priceCol ? $sheet->getCellByColumnAndRow($priceCol, $row)->getValue() : null;
            $tv = $tarifaCol ? $sheet->getCellByColumnAndRow($tarifaCol, $row)->getValue() : null;

            $names = $this->splitServicios($svStr);
            $qtyLines = $this->splitServicios($qv);
            $priceLines = $this->splitServicios($pv);

            foreach ($names as $i => $name) {
                $name = trim((string) $name);
                if ($name === '') continue;

                $servicios[] = [
                    'nombre_servicio' => $name,
                    'cantidad' => $qtyLines[$i] ?? $qv,
                    'tipo_tarifa' => $tv ? trim((string) $tv) : 'NORMAL',
                    'precio_unitario' => $priceLines[$i] ?? $pv,
                ];
            }
        }

        return $servicios;
    }

    /**
     * Detecta y extrae filas de un Excel export tipo "OT x Servicio" (export de órdenes)
     * Agrupa por columna Folio/OT y devuelve array folio => servicios[]
     */
    private function extraerServiciosDesdeTablaOtIndex(Worksheet $sheet): array
    {
        $highestRow = min($sheet->getHighestRow(), 2000);
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $headerRow = null;
        $folioCol = null;
        $serviceCol = null;
        $qtyCol = null;
        $priceCol = null;

        $facturaCol = null;
        $semanaCol = null;
        $costoUnitarioCol = null;
        $costoTotalCol = null;

        // Este detector debe ser estricto: sólo debe activarse para el Excel exportado de Órdenes
        // (headings como FACTURA, SEMANA, Folio/OT SOLGISTIKA, Proceso, Costo unitario, etc.).
        // La plantilla de Solicitud también trae "Folio" y "Tipo de Servicio" y no debe caer aquí.
        for ($row = 1; $row <= min($highestRow, 20); $row++) {
            $foundFolio = null; $foundService = null; $foundQty = null; $foundPrice = null;
            $foundFactura = null; $foundSemana = null; $foundCostoUnitario = null; $foundCostoTotal = null;
            for ($col = 1; $col <= min($highestColumnIndex, 40); $col++) {
                $v = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                if ($v === null || trim((string) $v) === '') continue;
                $n = $this->normalizar($v);

                if ($foundFactura === null && (Str::contains($n, 'factura'))) $foundFactura = $col;
                if ($foundSemana === null && ($n === 'semana' || Str::contains($n, 'semana'))) $foundSemana = $col;

                // En export de órdenes el encabezado suele ser "Folio/OT SOLGISTIKA".
                if ($foundFolio === null && (Str::contains($n, 'folio/ot') || (Str::contains($n, 'folio') && Str::contains($n, 'solgistika')))) {
                    $foundFolio = $col;
                }

                // En export de órdenes el servicio viene como "Proceso" (no "Tipo de Servicio").
                if ($foundService === null && (Str::contains($n, 'proceso') || $n === 'proceso')) {
                    $foundService = $col;
                }

                if ($foundQty === null && (Str::contains($n, 'cantidad') || Str::contains($n, 'pzs') || Str::contains($n, 'piezas'))) {
                    $foundQty = $col;
                }

                if ($foundCostoUnitario === null && (Str::contains($n, 'costo unitario') || Str::contains($n, 'precio unitario'))) {
                    $foundCostoUnitario = $col;
                }
                if ($foundCostoTotal === null && (Str::contains($n, 'costo total'))) {
                    $foundCostoTotal = $col;
                }

                // Compat: algunas versiones usan un solo campo de costo. Mantenemos $foundPrice como respaldo.
                if ($foundPrice === null && ($foundCostoUnitario !== null || $foundCostoTotal !== null)) {
                    $foundPrice = $foundCostoUnitario ?? $foundCostoTotal;
                }
            }

            // Heurística estricta para evitar falsos positivos:
            // - Requerimos Folio/OT + Proceso
            // - Y al menos 2 señales adicionales propias del export (FACTURA, SEMANA, costos)
            $signals = 0;
            if ($foundFactura !== null) $signals++;
            if ($foundSemana !== null) $signals++;
            if ($foundCostoUnitario !== null) $signals++;
            if ($foundCostoTotal !== null) $signals++;

            if ($foundFolio !== null && $foundService !== null && $signals >= 2) {
                $headerRow = $row;
                $folioCol = $foundFolio;
                $serviceCol = $foundService;
                $qtyCol = $foundQty;
                $priceCol = $foundCostoUnitario ?? $foundCostoTotal ?? $foundPrice;

                $facturaCol = $foundFactura;
                $semanaCol = $foundSemana;
                $costoUnitarioCol = $foundCostoUnitario;
                $costoTotalCol = $foundCostoTotal;
                break;
            }
        }

        if ($headerRow === null) return [];

        $grouped = [];
        $emptyStreak = 0;
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $folio = $sheet->getCellByColumnAndRow($folioCol, $row)->getValue();
            $svc = $sheet->getCellByColumnAndRow($serviceCol, $row)->getValue();
            if (($folio === null || trim((string)$folio) === '') && ($svc === null || trim((string)$svc) === '')) {
                $emptyStreak++; if ($emptyStreak >= 10) break; else continue;
            }
            $emptyStreak = 0;

            $folioKey = is_string($folio) ? trim($folio) : (string) $folio;
            $svcName = is_string($svc) ? trim($svc) : (string) $svc;
            if ($svcName === '') continue;

            $qv = $qtyCol ? $sheet->getCellByColumnAndRow($qtyCol, $row)->getValue() : null;
            $pv = $priceCol ? $sheet->getCellByColumnAndRow($priceCol, $row)->getValue() : null;

            if (!isset($grouped[$folioKey])) $grouped[$folioKey] = [];
            $grouped[$folioKey][] = [
                'nombre_servicio' => $svcName,
                'cantidad' => $qv,
                'tipo_tarifa' => 'NORMAL',
                'precio_unitario' => $pv,
            ];
        }

        return $grouped;
    }

    /**
     * Detecta y extrae filas del Excel de facturación (export) con columna "DATOS DE LA ORDEN DE TRABAJO".
     * Devuelve array ot_id => servicios[]
     *
     * Requisitos mínimos para considerarlo este formato:
     * - Encabezados: "DATOS DE LA ORDEN DE TRABAJO" + "Cantidad" + "Precio"
     *
     * En "DATOS..." se espera: "{NOMBRE_SERVICIO} OT: {OT_ID}".
     */
    private function extraerServiciosDesdeTablaFacturacionExport(Worksheet $sheet): array
    {
        $highestRow = min($sheet->getHighestRow(), 2000);
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $headerRow = null;
        $datosCol = null;
        $qtyCol = null;
        $priceCol = null;

        for ($row = 1; $row <= min($highestRow, 20); $row++) {
            $foundDatos = null;
            $foundQty = null;
            $foundPrice = null;

            for ($col = 1; $col <= min($highestColumnIndex, 40); $col++) {
                $v = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                if ($v === null || trim((string) $v) === '') continue;

                $n = $this->normalizar($v);
                if ($foundDatos === null && Str::contains($n, 'datos de la orden de trabajo')) {
                    $foundDatos = $col;
                }
                if ($foundQty === null && (Str::contains($n, 'cantidad') || $n === 'cantidad')) {
                    $foundQty = $col;
                }
                if ($foundPrice === null && ($n === 'precio' || Str::contains($n, 'precio'))) {
                    $foundPrice = $col;
                }
            }

            if ($foundDatos !== null && $foundQty !== null && $foundPrice !== null) {
                $headerRow = $row;
                $datosCol = $foundDatos;
                $qtyCol = $foundQty;
                $priceCol = $foundPrice;
                break;
            }
        }

        if ($headerRow === null) return [];

        $grouped = [];
        $emptyStreak = 0;

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $datosCell = $sheet->getCellByColumnAndRow($datosCol, $row)->getValue();
            $qtyCell = $sheet->getCellByColumnAndRow($qtyCol, $row)->getValue();
            $priceCell = $sheet->getCellByColumnAndRow($priceCol, $row)->getValue();

            $datosStr = trim((string) $datosCell);
            $qtyStr = trim((string) $qtyCell);
            $priceStr = trim((string) $priceCell);

            if ($datosStr === '' && $qtyStr === '' && $priceStr === '') {
                $emptyStreak++;
                if ($emptyStreak >= 10) break;
                continue;
            }
            $emptyStreak = 0;

            if ($datosStr === '') continue;

            if (!preg_match('/\bOT\s*:\s*(\d+)\b/i', $datosStr, $m)) {
                continue;
            }

            $otId = (string) $m[1];
            $svcName = trim(preg_replace('/\bOT\s*:\s*\d+\b/i', '', $datosStr));
            $svcName = trim(rtrim($svcName, '-:'));

            if ($svcName === '') {
                $svcName = 'Servicio';
            }

            $cantidad = is_numeric($qtyCell) ? (int) $qtyCell : (int) preg_replace('/[^0-9]/', '', $qtyStr);
            if ($cantidad <= 0) $cantidad = null;

            $precio = null;
            if (is_numeric($priceCell)) {
                $precio = (float) $priceCell;
            } elseif ($priceStr !== '') {
                $precio = (float) str_replace([',', '$'], '', $priceStr);
            }

            if (!isset($grouped[$otId])) $grouped[$otId] = [];
            $grouped[$otId][] = [
                'nombre_servicio' => $svcName,
                'cantidad' => $cantidad,
                'tipo_tarifa' => 'NORMAL',
                'precio_unitario' => $precio,
            ];
        }

        return $grouped;
    }

    private function splitServicios($value): array
    {
        if ($value === null) return [];
        if (!is_string($value)) $value = (string) $value;
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $parts = preg_split('/\n+/u', $value);
        $parts = array_map(fn($p) => trim((string) $p), $parts ?: []);
        return array_values(array_filter($parts, fn($p) => $p !== ''));
    }

    private function buscarPrimerValorAbajo(Worksheet $sheet, int $col, int $row, int $maxLookahead = 10)
    {
        $limit = $row + max(1, $maxLookahead);

        for ($r = $row + 1; $r <= $limit; $r++) {
            $v = $sheet->getCellByColumnAndRow($col, $r)->getValue();
            if ($v === null) continue;
            if (is_string($v)) {
                if (trim($v) === '') continue;
                return $v;
            }
            // Números/boolean/etc.
            $s = trim((string) $v);
            if ($s === '') continue;
            return $v;
        }

        return null;
    }

    /**
     * Extrae el valor si viene "Etiqueta: Valor" en la misma celda.
     */
    private function extraerValorInline($cellValue): ?string
    {
        if (!is_string($cellValue)) {
            $cellValue = (string) $cellValue;
        }

        $cellValue = trim($cellValue);
        if ($cellValue === '') return null;

        // Separadores comunes: ':' o '：'
        if (!Str::contains($cellValue, [':', '：'])) {
            return null;
        }

        $parts = preg_split('/[:：]/u', $cellValue, 2);
        if (!$parts || count($parts) < 2) return null;

        $after = trim((string) $parts[1]);
        return $after !== '' ? $after : null;
    }
    
    /**
     * Normalizar texto para comparación (trim, lowercase, sin acentos)
     * 
     * @param string|mixed $texto
     * @return string
     */
    private function normalizar($texto): string
    {
        if (!is_string($texto)) {
            $texto = (string) $texto;
        }
        
        $texto = trim($texto);
        $texto = mb_strtolower($texto, 'UTF-8');
        
        // Remover acentos
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $texto
        );
        
        return $texto;
    }
}
