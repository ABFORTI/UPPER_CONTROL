<?php

namespace App\Services;

use App\Models\CentroTrabajo;
use App\Models\CentroCosto;
use App\Models\Marca;
use App\Models\ServicioEmpresa;
use App\Models\Area;
use Illuminate\Support\Str;

/**
 * Servicio para mapear datos del Excel a IDs de la base de datos
 * Intenta encontrar coincidencias por código o nombre
 */
class OtPrefillMapper
{
    private array $warnings = [];
    
    /**
     * Mapear datos parseados del Excel a IDs de BD
     * 
     * @param array $datos Datos parseados del Excel
     * @return array ['prefill' => [...], 'warnings' => [...]]
     */
    public function map(array $datos): array
    {
        $this->warnings = [];
        
        $prefill = [
            'centro_trabajo_id' => $this->buscarCentroTrabajo($datos['centro'] ?? null),
            'centro_costos_id' => $this->buscarCentroCostos($datos['centro_costos'] ?? null),
            'marca_id' => $this->buscarMarca($datos['marca'] ?? null),
            'servicio_id' => $this->buscarServicio($datos['servicio'] ?? null),
            'area_id' => $this->buscarArea($datos['area'] ?? null),
            'descripcion' => $datos['descripcion_producto'] ?? $datos['descripcion'] ?? null,
            'cantidad' => $this->parsearCantidad($datos['cantidad'] ?? null),
            'solicitante' => $datos['solicitante'] ?? null,
            'upc' => $datos['upc'] ?? null,
        ];
        
        return [
            'prefill' => $prefill,
            'warnings' => $this->warnings
        ];
    }
    
    /**
     * Parsear cantidad (puede venir como texto)
     */
    private function parsearCantidad($valor): ?int
    {
        if (empty($valor)) return null;
        
        // Limpiar texto y extraer solo números
        $numero = preg_replace('/[^0-9]/', '', (string)$valor);
        
        return $numero ? (int)$numero : null;
    }
    
    /**
     * Buscar Centro de Trabajo por código o nombre
     */
    private function buscarCentroTrabajo(?string $texto): ?int
    {
        if (empty($texto)) return null;
        
        $centro = CentroTrabajo::where(function($q) use ($texto) {
            $q->where('codigo', 'LIKE', '%' . $texto . '%')
              ->orWhere('nombre', 'LIKE', '%' . $texto . '%');
        })->first();
        
        if (!$centro) {
            $this->warnings[] = "No se encontró Centro de Trabajo para: '{$texto}'";
        }
        
        return $centro?->id;
    }
    
    /**
     * Buscar Centro de Costos por código o nombre
     */
    private function buscarCentroCostos(?string $texto): ?int
    {
        if (empty($texto)) return null;
        
        // Extraer código si viene en formato "KPI 01" o "KPI01"
        $codigo = $this->extraerCodigo($texto);
        
        $centroCosto = CentroCosto::where(function($q) use ($texto, $codigo) {
            if ($codigo) {
                $q->where('codigo', 'LIKE', '%' . $codigo . '%');
            }
            $q->orWhere('nombre', 'LIKE', '%' . $texto . '%');
        })->first();
        
        if (!$centroCosto) {
            $this->warnings[] = "No se encontró Centro de Costos para: '{$texto}'";
        }
        
        return $centroCosto?->id;
    }
    
    /**
     * Buscar Marca por nombre
     */
    private function buscarMarca(?string $texto): ?int
    {
        if (empty($texto)) return null;
        
        $marca = Marca::where('nombre', 'LIKE', '%' . $texto . '%')->first();
        
        if (!$marca) {
            $this->warnings[] = "No se encontró Marca en catálogo para: '{$texto}'";
        }
        
        return $marca?->id;
    }
    
    /**
     * Buscar Servicio por código o nombre
     */
    private function buscarServicio(?string $texto): ?int
    {
        if (empty($texto)) return null;
        
        $codigo = $this->extraerCodigo($texto);
        
        $servicio = ServicioEmpresa::where(function($q) use ($texto, $codigo) {
            if ($codigo) {
                $q->where('codigo', 'LIKE', '%' . $codigo . '%');
            }
            $q->orWhere('nombre', 'LIKE', '%' . $texto . '%');
        })->first();
        
        if (!$servicio) {
            $this->warnings[] = "No se encontró Servicio para: '{$texto}'";
        }
        
        return $servicio?->id;
    }
    
    /**
     * Buscar Área por nombre
     */
    private function buscarArea(?string $texto): ?int
    {
        if (empty($texto)) return null;
        
        $area = Area::where('nombre', 'LIKE', '%' . $texto . '%')->first();
        
        if (!$area) {
            $this->warnings[] = "No se encontró Área para: '{$texto}' (campo opcional)";
        }
        
        return $area?->id;
    }
    
    /**
     * Extraer código de un texto (ej: "KPI 01" -> "KPI01")
     */
    private function extraerCodigo(string $texto): ?string
    {
        // Buscar patrones como "KPI 01", "KPI-01", etc.
        if (preg_match('/([A-Z]+)\s*-?\s*(\d+)/i', $texto, $matches)) {
            return strtoupper($matches[1]) . $matches[2];
        }
        
        return null;
    }
}
