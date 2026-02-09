<?php

namespace App\Http\Controllers;

use App\Models\Orden;
use App\Services\ExcelOtParser;
use App\Services\OtPrefillMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OrdenExcelController extends Controller
{
    protected ExcelOtParser $parser;
    protected OtPrefillMapper $mapper;
    
    public function __construct(ExcelOtParser $parser, OtPrefillMapper $mapper)
    {
        $this->parser = $parser;
        $this->mapper = $mapper;
    }
    
    /**
     * Subir archivo Excel y devolver datos para precargar formulario
     * 
     * @param Request $request
     * @param Orden $orden
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request, Orden $orden)
    {
        // Autorización: solo roles permitidos o dueño de la OT
        $this->authorize('update', $orden);
        
        // Validar archivo
        $request->validate([
            'excel' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:10240', // 10MB máximo
            ]
        ], [
            'excel.required' => 'Debe seleccionar un archivo Excel',
            'excel.mimes' => 'El archivo debe ser formato .xlsx o .xls',
            'excel.max' => 'El archivo no puede superar 10MB'
        ]);
        
        $file = $request->file('excel');
        
        try {
            // Generar nombre único para el archivo
            $filename = 'OT_' . $orden->id . '_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            
            // Guardar archivo en storage/app/public/ot_archivos/
            $path = $file->storeAs('public/ot_archivos', $filename);
            $publicPath = 'ot_archivos/' . $filename;
            
            // Si ya existe un archivo previo, eliminarlo
            if ($orden->archivo_excel_path && Storage::exists('public/' . $orden->archivo_excel_path)) {
                Storage::delete('public/' . $orden->archivo_excel_path);
            }
            
            // Parsear el Excel
            $absolutePath = Storage::path($path);
            $datosParseados = $this->parser->parse($absolutePath);
            
            // Mapear datos a IDs
            $resultado = $this->mapper->map($datosParseados);
            
            // Guardar metadata del archivo en la BD
            $orden->update([
                'archivo_excel_path' => $publicPath,
                'archivo_excel_nombre_original' => $file->getClientOriginalName(),
                'archivo_excel_mime' => $file->getMimeType(),
                'archivo_excel_size' => $file->getSize(),
                'archivo_excel_subido_por' => Auth::id(),
                'archivo_excel_subido_at' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'archivo' => [
                    'nombre' => $file->getClientOriginalName(),
                    'url_descarga' => route('ordenes.archivo.download', $orden->id),
                    'fecha_subida' => now()->format('d/m/Y H:i'),
                    'subido_por' => Auth::user()->name,
                ],
                'prefill' => $resultado['prefill'],
                'warnings' => $resultado['warnings'],
            ]);
            
        } catch (\Exception $e) {
            // Si ocurre un error, eliminar el archivo si se subió
            if (isset($path) && Storage::exists($path)) {
                Storage::delete($path);
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 422);
        }
    }
    
    /**
     * Descargar archivo Excel de una OT
     * 
     * @param Orden $orden
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download(Orden $orden)
    {
        // Autorización
        $this->authorize('view', $orden);
        
        if (!$orden->archivo_excel_path) {
            abort(404, 'No hay archivo Excel para esta orden');
        }
        
        $fullPath = Storage::path('public/' . $orden->archivo_excel_path);
        
        if (!file_exists($fullPath)) {
            abort(404, 'Archivo no encontrado');
        }
        
        return response()->download(
            $fullPath,
            $orden->archivo_excel_nombre_original ?? 'archivo.xlsx'
        );
    }
}
