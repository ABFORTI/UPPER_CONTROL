<?php

// app/Http/Controllers/EvidenciaController.php
namespace App\Http\Controllers;

use App\Models\Evidencia;
use App\Models\Avance;
use App\Models\Orden;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class EvidenciaController extends Controller
{
  public function store(Request $req, Orden $orden)
  {
    $this->authorize('gestionarEvidencias', $orden);

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
    $orden = $evidencia->orden;
    $this->authorize('gestionarEvidencias', $orden);

    Storage::disk('public')->delete($evidencia->path);
    $evidencia->delete();

    return back()->with('ok','Evidencia eliminada');
  }

  public function downloadAll(Orden $orden)
  {
    $this->authorize('view', $orden);

    $evidencias = $orden->evidencias()->orderBy('id')->get();
    if ($evidencias->isEmpty()) {
      return back()->withErrors(['evidencias' => 'No hay evidencias para descargar en esta OT.']);
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'ot_evid_');
    if ($tmpFile === false) {
      return back()->withErrors(['evidencias' => 'No fue posible preparar la descarga.']);
    }

    $zipPath = $tmpFile . '.zip';
    @rename($tmpFile, $zipPath);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
      @unlink($zipPath);
      return back()->withErrors(['evidencias' => 'No fue posible generar el archivo ZIP.']);
    }

    $usedNames = [];
    foreach ($evidencias as $ev) {
      $diskPath = (string)($ev->path ?? '');
      if ($diskPath === '' || !Storage::disk('public')->exists($diskPath)) {
        continue;
      }

      $source = Storage::disk('public')->path($diskPath);
      $baseName = trim((string)($ev->original_name ?: basename($diskPath)));
      if ($baseName === '') {
        $baseName = 'evidencia_'.$ev->id;
      }

      $entryName = $baseName;
      $n = 2;
      while (isset($usedNames[strtolower($entryName)])) {
        $dotPos = strrpos($baseName, '.');
        if ($dotPos !== false) {
          $name = substr($baseName, 0, $dotPos);
          $ext  = substr($baseName, $dotPos);
          $entryName = $name.'_'.$n.$ext;
        } else {
          $entryName = $baseName.'_'.$n;
        }
        $n++;
      }
      $usedNames[strtolower($entryName)] = true;

      $zip->addFile($source, $entryName);
    }

    $zip->close();

    if (!file_exists($zipPath) || filesize($zipPath) === 0) {
      @unlink($zipPath);
      return back()->withErrors(['evidencias' => 'No se encontraron archivos físicos de evidencia para descargar.']);
    }

    $filename = 'OT_'.$orden->id.'_evidencias_'.now()->format('Ymd_His').'.zip';
    return response()->download($zipPath, $filename)->deleteFileAfterSend(true);
  }
}
