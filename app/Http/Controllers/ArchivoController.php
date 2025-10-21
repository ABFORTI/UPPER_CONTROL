<?php

namespace App\Http\Controllers;

use App\Models\Archivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArchivoController extends Controller
{
    /**
     * Descargar archivo con nombre original
     */
    public function download(Archivo $archivo)
    {
        // Verificar que el archivo existe
        if (!Storage::disk('public')->exists($archivo->path)) {
            abort(404, 'Archivo no encontrado');
        }

        // Obtener el nombre original o generar uno desde el path
        $nombreDescarga = $archivo->nombre_original ?: basename($archivo->path);

        // Descargar con nombre original
        return Storage::disk('public')->download($archivo->path, $nombreDescarga);
    }

    /**
     * Ver archivo en línea (para imágenes y PDFs)
     */
    public function view(Archivo $archivo)
    {
        // Verificar que el archivo existe
        if (!Storage::disk('public')->exists($archivo->path)) {
            abort(404, 'Archivo no encontrado');
        }

        $path = Storage::disk('public')->path($archivo->path);
        $mimeType = $archivo->mime ?: Storage::disk('public')->mimeType($archivo->path);

        return response()->file($path, [
            'Content-Type' => $mimeType,
        ]);
    }
}
