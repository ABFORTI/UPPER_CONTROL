<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExcelOtParser;
use App\Services\OtPrefillMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SolicitudExcelController extends Controller
{
    public function parseExcel(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $file = $request->file('archivo');
            $tempPath = $file->getRealPath();

            // Parsear Excel
            $parser = new ExcelOtParser();
            $datos = $parser->parse($tempPath);

            // Mapear datos a IDs
            $mapper = new OtPrefillMapper();
            $resultado = $mapper->map($datos);

            return response()->json([
                'success' => true,
                'prefill' => $resultado['prefill'],
                'warnings' => $resultado['warnings'],
                'datos_extraidos' => $datos, // Para debug
            ]);

        } catch (\Exception $e) {
            Log::error('Error al parsear Excel de solicitud: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }
}
