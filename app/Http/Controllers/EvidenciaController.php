<?php

// app/Http/Controllers/EvidenciaController.php
namespace App\Http\Controllers;

use App\Models\Evidencia;
use App\Models\Orden;
use App\Models\OrdenItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EvidenciaController extends Controller
{
  public function store(Request $req, Orden $orden)
  {
    // Misma regla que “reportarAvance”: admin o TL asignado
    $this->authorize('reportarAvance', $orden);

    $data = $req->validate([
      'id_item'      => ['nullable','integer','exists:orden_items,id'],
      'evidencias'   => ['required','array','min:1'],
      'evidencias.*' => ['file','max:10240', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf,video/mp4'],
    ]);

    $saved = [];
    foreach ($req->file('evidencias', []) as $file) {
      $path = $file->store("evidencias/orden-{$orden->id}", 'public');
      $saved[] = Evidencia::create([
        'id_orden'      => $orden->id,
        'id_item'       => $data['id_item'] ?? null,
        'id_usuario'    => $req->user()->id,
        'path'          => $path,
        'original_name' => $file->getClientOriginalName(),
        'mime'          => $file->getClientMimeType(),
        'size'          => $file->getSize(),
      ]);
    }

    return back()->with('ok', count($saved).' evidencias cargadas');
  }

  public function destroy(Evidencia $evidencia)
  {
    // Permite borrar a admin o TL de la orden
    $orden = $evidencia->orden;
    $this->authorize('reportarAvance', $orden);

    Storage::disk('public')->delete($evidencia->path);
    $evidencia->delete();

    return back()->with('ok','Evidencia eliminada');
  }
}
