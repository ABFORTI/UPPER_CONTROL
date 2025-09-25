<?php

// app/Http/Controllers/PrecioController.php
namespace App\Http\Controllers;

use App\Http\Requests\PreciosSaveRequest;
use App\Models\CentroTrabajo;
use App\Models\ServicioEmpresa;
use App\Models\ServicioCentro;
use App\Models\ServicioTamano;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Inertia\Inertia;

class PrecioController extends Controller
{
  // Lista/edición por centro
  public function index(Request $req) {
    $this->authorizeAdminOrCoord();

    $centros = CentroTrabajo::select('id','nombre')->orderBy('nombre')->get();

    $idCentro = (int)($req->integer('centro') ?: ($req->user()->centro_trabajo_id ?? ($centros->first()->id ?? 1)));

    $servicios = ServicioEmpresa::select('id','nombre','usa_tamanos')->orderBy('nombre')->get();

    // Trae precios existentes en un map servicio_id => datos
    $porCentro = ServicioCentro::with('tamanos')
      ->where('id_centrotrabajo',$idCentro)->get()
      ->keyBy('id_servicio');

    // Compacta para el front
    $rows = $servicios->map(function($s) use ($porCentro){
      $sc = $porCentro->get($s->id);
      return [
        'id_servicio' => $s->id,
        'servicio'    => $s->nombre,
        'usa_tamanos' => (bool)$s->usa_tamanos,
        'precio_base' => $sc?->precio_base,
        'tamanos'     => [
          'chico'   => optional($sc?->tamanos->firstWhere('tamano','chico'))->precio,
          'mediano' => optional($sc?->tamanos->firstWhere('tamano','mediano'))->precio,
          'grande'  => optional($sc?->tamanos->firstWhere('tamano','grande'))->precio,
          'jumbo'   => optional($sc?->tamanos->firstWhere('tamano','jumbo'))->precio,
        ],
      ];
    });

    return Inertia::render('Precios/Index', [
      'centros'  => $centros,
      'centroId' => $idCentro,
      'rows'     => $rows,
      'urls'     => [
        'index'  => route('precios.index'),
        'guardar'=> route('precios.guardar'),
      ],
    ]);
  }

  // Guardar cambios (en bloque)
  public function guardar(PreciosSaveRequest $req) {
    $this->authorizeAdminOrCoord();

    DB::transaction(function () use ($req) {
      $centroId = (int)$req->id_centro;

      foreach ($req->items as $item) {
        $servicioId = (int)$item['id_servicio'];
        $usaTamanos = (bool)$item['usa_tamanos'];

        // upsert del ServicioCentro
        $sc = ServicioCentro::updateOrCreate(
          ['id_centrotrabajo'=>$centroId, 'id_servicio'=>$servicioId],
          ['precio_base'=> $usaTamanos ? 0 : (float)($item['precio_base'] ?? 0)]
        );

        if ($usaTamanos) {
          foreach (['chico','mediano','grande','jumbo'] as $t) {
            $precio = (float)($item['tamanos'][$t] ?? 0);
            ServicioTamano::updateOrCreate(
              ['id_servicio_centro'=>$sc->id, 'tamano'=>$t],
              ['precio'=>$precio]
            );
          }
        } else {
          // Limpia tamaños si el servicio no usa_tamanos
          ServicioTamano::where('id_servicio_centro',$sc->id)->delete();
        }
      }
    });

    return back()->with('ok','Precios actualizados');
  }

  private function authorizeAdminOrCoord(): void {
    /** @var User|null $u */
    $u = Auth::user();
    if (!$u) abort(403);
    if ($u->hasRole('admin') || $u->hasRole('coordinador')) return;
    abort(403);
  }
}
