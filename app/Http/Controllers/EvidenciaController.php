<?php

// app/Http/Controllers/EvidenciaController.php
namespace App\Http\Controllers;

use App\Models\Evidencia;
use App\Models\Avance;
use App\Models\Orden;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EvidenciaController extends Controller
{
  public function store(Request $req, Orden $orden)
  {
    // Misma regla que “reportarAvance”: admin o TL asignado
    $this->authorize('reportarAvance', $orden);

    $data = $req->validate([
      'avance_id'    => ['required','integer','exists:avances,id'],
      'files'        => ['required_without:evidencias','array','min:1'],
      'evidencias'   => ['required_without:files','array','min:1'],
      'files.*'      => ['file','max:10240', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf,video/mp4'],
      'evidencias.*' => ['file','max:10240', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf,video/mp4'],
    ]);

    $avance = Avance::query()
      ->where('id', (int)$data['avance_id'])
      ->where('id_orden', $orden->id)
      ->first();

    if (!$avance) {
      return back()->withErrors(['avance_id' => 'El avance seleccionado no pertenece a esta OT.']);
    }

    $saved = [];
    $files = $req->file('files', $req->file('evidencias', []));
    foreach ($files as $file) {
      $path = $file->store("evidencias/orden-{$orden->id}", 'public');
      $saved[] = Evidencia::create([
        'id_orden'      => $orden->id,
        'id_item'       => $avance->id_item,
        'avance_id'     => $avance->id,
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
