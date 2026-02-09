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
