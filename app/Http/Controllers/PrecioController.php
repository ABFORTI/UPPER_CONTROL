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

    // Solo servicios que YA están vinculados al centro seleccionado
    $scRows = ServicioCentro::with(['tamanos','servicio:id,nombre,usa_tamanos'])
      ->where('id_centrotrabajo',$idCentro)
      ->orderBy(
        ServicioEmpresa::select('nombre')->whereColumn('servicios_empresa.id','servicios_centro.id_servicio')
      )
      ->get();

    // Compacta para el front (solo los del centro)
    $rows = $scRows->map(function($sc){
      $s = $sc->servicio;
      return [
        'id_servicio' => $s->id,
        'servicio'    => $s->nombre,
        'usa_tamanos' => (bool)$s->usa_tamanos,
        'precio_base' => $sc->precio_base,
        'tamanos'     => [
          'chico'   => optional($sc->tamanos->firstWhere('tamano','chico'))->precio,
          'mediano' => optional($sc->tamanos->firstWhere('tamano','mediano'))->precio,
          'grande'  => optional($sc->tamanos->firstWhere('tamano','grande'))->precio,
          'jumbo'   => optional($sc->tamanos->firstWhere('tamano','jumbo'))->precio,
        ],
        // campos auxiliares para edición rápida en la tabla
        '_unitario' => $sc->precio_base,
        '_chico'    => optional($sc->tamanos->firstWhere('tamano','chico'))->precio,
        '_mediano'  => optional($sc->tamanos->firstWhere('tamano','mediano'))->precio,
        '_grande'   => optional($sc->tamanos->firstWhere('tamano','grande'))->precio,
        '_jumbo'    => optional($sc->tamanos->firstWhere('tamano','jumbo'))->precio,
      ];
    });

    return Inertia::render('Servicios/Index', [
      'centros'  => $centros,
      'centroId' => $idCentro,
      'rows'     => $rows,
      'urls'     => [
        'index'   => route('servicios.index'),
        'guardar' => route('servicios.guardar'),
        'crear'   => route('servicios.crear'), // POST
        'create'  => route('servicios.create'), // GET form
        'clonar'  => route('servicios.clonar'),
        'eliminar'=> route('servicios.eliminar'),
      ],
    ]);
  }

  // Formulario: crear servicio (GET)
  public function create(Request $req) {
    $this->authorizeAdminOrCoord();

    $centros = CentroTrabajo::select('id','nombre')->orderBy('nombre')->get();
    $idCentro = (int)($req->integer('centro') ?: ($req->user()->centro_trabajo_id ?? ($centros->first()->id ?? 1)));

    return Inertia::render('Servicios/Create', [
      'centros'  => $centros,
      'centroId' => $idCentro,
      'urls'     => [
        'index' => route('servicios.index'),
        'crear' => route('servicios.crear'), // POST
      ],
    ]);
  }

  // Guardar cambios (en bloque)
  public function guardar(PreciosSaveRequest $req) {
    $this->authorizeAdminOrCoord();

    DB::transaction(function () use ($req) {
  $centroId = (int)request()->input('id_centro');

  foreach ((array)request()->input('items', []) as $item) {
        $servicioId = (int)$item['id_servicio'];
        $usaTamanos = (bool)$item['usa_tamanos'];

    // Alinear el modo del servicio a nivel global con lo elegido en la pantalla de precios
    // para que el resto del sistema (por ejemplo, el selector en Solicitudes) muestre
    // correctamente "Unitario" vs "Por tamaños".
    ServicioEmpresa::where('id', $servicioId)->update(['usa_tamanos' => $usaTamanos]);

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

  // Crear un nuevo servicio y registrar sus precios para un centro seleccionado
  public function crear(Request $req)
  {
    $this->authorizeAdminOrCoord();
    $data = $req->validate([
      'nombre'      => ['required','string','max:150'],
      'usa_tamanos' => ['required','boolean'],
      'id_centro'   => ['required','integer','exists:centros_trabajo,id'],
      'precio_base' => ['nullable','numeric','min:0'],
      'tamanos'     => ['nullable','array'],
      'tamanos.chico'   => ['nullable','numeric','min:0'],
      'tamanos.mediano' => ['nullable','numeric','min:0'],
      'tamanos.grande'  => ['nullable','numeric','min:0'],
      'tamanos.jumbo'   => ['nullable','numeric','min:0'],
    ]);

    // Crear servicio (si no existe)
    $servicio = ServicioEmpresa::firstOrCreate(
      ['nombre' => $data['nombre']],
      ['usa_tamanos' => (bool)$data['usa_tamanos']]
    );

    // Crear/actualizar precios solo para el centro seleccionado
    $sc = ServicioCentro::updateOrCreate(
      ['id_centrotrabajo' => (int)$data['id_centro'], 'id_servicio' => $servicio->id],
      ['precio_base' => $data['usa_tamanos'] ? 0 : (float)($data['precio_base'] ?? 0)]
    );

    if ($data['usa_tamanos']) {
      $t = $data['tamanos'] ?? [];
      foreach (['chico','mediano','grande','jumbo'] as $tam) {
        $precio = (float)($t[$tam] ?? 0);
        ServicioTamano::updateOrCreate(
          ['id_servicio_centro' => $sc->id, 'tamano' => $tam],
          ['precio' => $precio]
        );
      }
    } else {
      // Si es unitario, limpiar cualquier tamaño previo por si cambió la configuración
      ServicioTamano::where('id_servicio_centro', $sc->id)->delete();
    }

    // Redirige al listado al centro elegido
    return redirect()
      ->route('servicios.index', ['centro' => (int)$data['id_centro']])
      ->with('ok', 'Servicio creado y precios registrados');
  }

  // Clona servicios del centro origen al destino (solo los que no existen en destino)
  public function clonar(Request $req)
  {
    $this->authorizeAdminOrCoord();
    $data = $req->validate([
      'centro_origen'  => ['required','integer','different:centro_destino','exists:centros_trabajo,id'],
      'centro_destino' => ['required','integer','exists:centros_trabajo,id'],
    ]);

    $origen  = (int)$data['centro_origen'];
    $destino = (int)$data['centro_destino'];

    DB::transaction(function () use ($origen, $destino) {
      $existentes = ServicioCentro::where('id_centrotrabajo', $destino)->pluck('id_servicio')->all();
      $copiar = ServicioCentro::with('tamanos')
        ->where('id_centrotrabajo', $origen)
        ->get();
      foreach ($copiar as $sc) {
        if (in_array($sc->id_servicio, $existentes, true)) continue; // no duplicar
        $nuevo = ServicioCentro::create([
          'id_centrotrabajo' => $destino,
          'id_servicio'      => $sc->id_servicio,
          'precio_base'      => $sc->precio_base,
        ]);
        foreach ($sc->tamanos as $t) {
          ServicioTamano::create([
            'id_servicio_centro' => $nuevo->id,
            'tamano'             => $t->tamano,
            'precio'             => $t->precio,
          ]);
        }
      }
    });

    return back()->with('ok','Servicios clonados');
  }

  // Eliminar un servicio únicamente del centro indicado (no afecta otros centros)
  public function eliminar(Request $req)
  {
    $this->authorizeAdminOrCoord();
    $data = $req->validate([
      'id_centro'   => ['required','integer','exists:centros_trabajo,id'],
      'id_servicio' => ['required','integer','exists:servicios_empresa,id'],
    ]);

    DB::transaction(function () use ($data) {
      $sc = ServicioCentro::where('id_centrotrabajo', (int)$data['id_centro'])
        ->where('id_servicio', (int)$data['id_servicio'])
        ->first();
      if ($sc) {
        ServicioTamano::where('id_servicio_centro', $sc->id)->delete();
        $sc->delete();
      }
    });

    return back()->with('ok','Servicio eliminado del centro');
  }

  private function authorizeAdminOrCoord(): void {
    /** @var User|null $u */
    $u = Auth::user();
    if (!$u) abort(403);
    if ($u->hasAnyRole(['admin', 'coordinador', 'control', 'comercial'])) return;
    // Gerente Upper: permitir solo lectura (GET/HEAD/OPTIONS)
    if ($u->hasRole('gerente_upper')) {
      $m = strtoupper(request()->method());
      if (in_array($m, ['GET','HEAD','OPTIONS'], true)) return;
      abort(403);
    }
    abort(403);
  }
}
