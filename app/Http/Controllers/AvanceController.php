<?php

// app/Http/Controllers/AvanceController.php
namespace App\Http\Controllers;

use App\Http\Requests\AvanceStoreRequest;
use App\Models\Orden;
use App\Models\OrdenItem;
use App\Models\Avance;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class AvanceController extends Controller
{
  public function store(AvanceStoreRequest $req, Orden $orden) {
    $this->authorize('reportarAvance', $orden);
    // Sólo TL asignado, o admin
    $u = $req->user();
    if (!$u->hasAnyRole(['admin']) && $orden->team_leader_id !== $u->id) abort(403);

    return DB::transaction(function () use ($req, $orden, $u) {
      $item = null;
      if ($req->filled('id_item')) {
        $item = OrdenItem::where('id',$req->id_item)->where('id_orden',$orden->id)->firstOrFail();
        // No permitir exceder la planeada
        if ($item->cantidad_real + $req->cantidad > $item->cantidad_planeada) {
          abort(422, 'La cantidad excede lo planeado para el ítem.');
        }
      }

      $path = null;
      if ($req->hasFile('evidencia')) {
        $path = $req->file('evidencia')->store("ordenes/{$orden->id}/avances", 'public');
      }

      // Marcar como corregido SOLO si:
      // 1. Existe un rechazo de calidad registrado para esta orden
      // 2. Y ese rechazo fue DESPUÉS de que ya había avances (es decir, es una corrección real)
      $rechazoCalidad = \App\Models\Aprobacion::where('aprobable_type', \App\Models\Orden::class)
        ->where('aprobable_id', $orden->id)
        ->where('tipo', 'calidad')
        ->where('resultado', 'rechazado')
        ->latest()
        ->first();
      
      // Solo es corregido si hay un rechazo Y había avances antes de ese rechazo
      $isCorregido = false;
      if ($rechazoCalidad) {
        $avancesAntesDeRechazo = \App\Models\Avance::where('id_orden', $orden->id)
          ->where('created_at', '<', $rechazoCalidad->created_at)
          ->exists();
        $isCorregido = $avancesAntesDeRechazo;
      }

      Avance::create([
        'id_orden'  => $orden->id,
        'id_item'   => $item?->id,
        'id_usuario'=> $u->id,
        'cantidad'  => (int)$req->cantidad,
        'comentario'=> $req->comentario ?? null,
        'evidencia_url' => $path,
        'es_corregido' => $isCorregido,
      ]);

      // Actualiza acumulados
      if ($item) {
        $item->increment('cantidad_real', (int)$req->cantidad);
      }

      // ¿Se completaron todos los items?
      $incompletos = $orden->items()->whereColumn('cantidad_real','<','cantidad_planeada')->exists();
      if (!$incompletos) {
        $orden->update(['estatus'=>'completada']);
        // Aquí luego: notificar a Calidad
      } else {
        // Si aún no está en proceso, pásala a en_proceso
        if ($orden->estatus !== 'en_proceso') $orden->update(['estatus'=>'en_proceso']);
      }

      return back()->with('ok','Avance registrado');
    });
  }
}
