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
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Models\User;
use Inertia\Inertia;

class PrecioController extends Controller
{
  // Lista/edición por centro
  public function index(Request $req) {
    $this->authorizeAdminOrCoord();

    $centros = CentroTrabajo::select('id','nombre')->orderBy('nombre')->get();

    $idCentro = (int)($req->integer('centro') ?: ($req->user()->centro_trabajo_id ?? ($centros->first()->id ?? 1)));

    $hasNombreCol = Schema::hasColumn('servicios_centro', 'nombre');
    $hasUsaTamanosCol = Schema::hasColumn('servicios_centro', 'usa_tamanos');

    // Solo servicios que YA están vinculados al centro seleccionado
    $scRowsQuery = ServicioCentro::with(['tamanos','servicio:id,nombre,usa_tamanos'])
      ->where('id_centrotrabajo',$idCentro);

    if ($hasNombreCol) {
      $scRowsQuery->orderBy('nombre');
    } else {
      $scRowsQuery->orderBy(
        ServicioEmpresa::select('nombre')->whereColumn('servicios_empresa.id','servicios_centro.id_servicio')
      );
    }

    $scRows = $scRowsQuery->get();

    // Compacta para el front (solo los del centro)
    $rows = $scRows->map(function($sc) use ($hasNombreCol, $hasUsaTamanosCol) {
      $s = $sc->servicio;

      $usaTamanosCentro = $hasUsaTamanosCol
        ? (bool)$sc->usa_tamanos
        : (bool)$s->usa_tamanos;

      return [
        'id_servicio' => $s->id,
        'servicio'    => $hasNombreCol ? ($sc->nombre ?: $s->nombre) : $s->nombre,
        'usa_tamanos' => $usaTamanosCentro,
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

    $hasNombreCol = Schema::hasColumn('servicios_centro', 'nombre');
    $hasUsaTamanosCol = Schema::hasColumn('servicios_centro', 'usa_tamanos');

    DB::transaction(function () use ($req, $hasNombreCol, $hasUsaTamanosCol) {
      $centroId = (int)$req->input('id_centro');

      foreach ((array)$req->input('items', []) as $item) {
        $servicioId = (int)$item['id_servicio'];
        $usaTamanos = (bool)$item['usa_tamanos'];
        $servicio = ServicioEmpresa::find($servicioId);
        if (!$servicio) {
          continue;
        }

        // upsert del ServicioCentro
        $updateData = [
          'precio_base' => $usaTamanos ? 0 : (float)($item['precio_base'] ?? 0),
        ];

        if ($hasNombreCol) {
          $updateData['nombre'] = trim((string)$servicio->nombre);
        }
        if ($hasUsaTamanosCol) {
          $updateData['usa_tamanos'] = $usaTamanos;
        }

        $sc = ServicioCentro::updateOrCreate(
          ['id_centrotrabajo'=>$centroId, 'id_servicio'=>$servicioId],
          $updateData
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
    $hasNombreCol = Schema::hasColumn('servicios_centro', 'nombre');
    $hasUsaTamanosCol = Schema::hasColumn('servicios_centro', 'usa_tamanos');

    $data = $req->validate([
      'nombre'      => ['required','string','max:150'],
      'usa_tamanos' => ['required','boolean'],
      'id_centro'   => ['required','integer','exists:centros_trabajo,id'],
      'precio_base' => ['nullable','numeric','min:0', Rule::requiredIf(fn () => !$req->boolean('usa_tamanos'))],
      'tamanos'     => ['nullable','array'],
      'tamanos.chico'   => ['nullable','numeric','min:0', Rule::requiredIf(fn () => $req->boolean('usa_tamanos'))],
      'tamanos.mediano' => ['nullable','numeric','min:0', Rule::requiredIf(fn () => $req->boolean('usa_tamanos'))],
      'tamanos.grande'  => ['nullable','numeric','min:0', Rule::requiredIf(fn () => $req->boolean('usa_tamanos'))],
      'tamanos.jumbo'   => ['nullable','numeric','min:0', Rule::requiredIf(fn () => $req->boolean('usa_tamanos'))],
    ]);

    $nombreNormalizado = trim((string)$data['nombre']);

    $duplicadoEnCentro = $hasNombreCol
      ? ServicioCentro::query()
          ->where('id_centrotrabajo', (int)$data['id_centro'])
          ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($nombreNormalizado)])
          ->exists()
      : ServicioCentro::query()
          ->where('id_centrotrabajo', (int)$data['id_centro'])
          ->whereHas('servicio', function ($q) use ($nombreNormalizado) {
            $q->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($nombreNormalizado)]);
          })
          ->exists();

    if ($duplicadoEnCentro) {
      return back()->withErrors([
        'nombre' => 'Ya existe un servicio con ese nombre en el centro seleccionado.',
      ])->withInput();
    }

    // Crear siempre un registro de servicio independiente.
    $servicio = ServicioEmpresa::create([
      'nombre' => $nombreNormalizado,
      'usa_tamanos' => (bool)$data['usa_tamanos'],
    ]);

    // Crear/actualizar precios solo para el centro seleccionado
    $scPayload = [
      'id_centrotrabajo' => (int)$data['id_centro'],
      'id_servicio' => $servicio->id,
      'precio_base' => $data['usa_tamanos'] ? 0 : (float)($data['precio_base'] ?? 0),
    ];

    if ($hasNombreCol) {
      $scPayload['nombre'] = $nombreNormalizado;
    }

    if ($hasUsaTamanosCol) {
      $scPayload['usa_tamanos'] = (bool)$data['usa_tamanos'];
    }

    $sc = ServicioCentro::create($scPayload);

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
    $hasNombreCol = Schema::hasColumn('servicios_centro', 'nombre');
    $hasUsaTamanosCol = Schema::hasColumn('servicios_centro', 'usa_tamanos');

    $data = $req->validate([
      'centro_origen'  => ['required','integer','different:centro_destino','exists:centros_trabajo,id'],
      'centro_destino' => ['required','integer','exists:centros_trabajo,id'],
    ]);

    $origen  = (int)$data['centro_origen'];
    $destino = (int)$data['centro_destino'];

    DB::transaction(function () use ($origen, $destino, $hasNombreCol, $hasUsaTamanosCol) {
      $existentes = $hasNombreCol
        ? ServicioCentro::where('id_centrotrabajo', $destino)
            ->pluck('nombre')
            ->map(fn ($n) => mb_strtolower(trim((string)$n)))
            ->filter()
            ->values()
            ->all()
        : ServicioCentro::with('servicio:id,nombre')
            ->where('id_centrotrabajo', $destino)
            ->get()
            ->map(fn ($row) => mb_strtolower(trim((string)($row->servicio?->nombre ?? ''))))
            ->filter()
            ->values()
            ->all();

      $copiar = ServicioCentro::with(['tamanos','servicio:id,nombre,usa_tamanos'])
        ->where('id_centrotrabajo', $origen)
        ->get();

      foreach ($copiar as $sc) {
        $nombre = trim((string)($sc->nombre ?: $sc->servicio?->nombre));
        $nombreKey = mb_strtolower($nombre);
        if (!$nombre || in_array($nombreKey, $existentes, true)) continue; // no duplicar por nombre en el mismo centro

        $usaTamanosOrigen = $hasUsaTamanosCol ? (bool)$sc->usa_tamanos : (bool)($sc->servicio?->usa_tamanos);

        $servicioNuevo = ServicioEmpresa::create([
          'nombre' => $nombre,
          'usa_tamanos' => $usaTamanosOrigen,
        ]);

        $nuevoPayload = [
          'id_centrotrabajo' => $destino,
          'id_servicio'      => $servicioNuevo->id,
          'precio_base'      => $sc->precio_base,
        ];

        if ($hasNombreCol) {
          $nuevoPayload['nombre'] = $nombre;
        }
        if ($hasUsaTamanosCol) {
          $nuevoPayload['usa_tamanos'] = $usaTamanosOrigen;
        }

        $nuevo = ServicioCentro::create($nuevoPayload);
        foreach ($sc->tamanos as $t) {
          ServicioTamano::create([
            'id_servicio_centro' => $nuevo->id,
            'tamano'             => $t->tamano,
            'precio'             => $t->precio,
          ]);
        }

        $existentes[] = $nombreKey;
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
        $servicioId = $sc->id_servicio;
        $sc->delete();

        // Limpia catalogo huerfano (si ese id_servicio ya no esta ligado a ningun centro)
        if (!ServicioCentro::where('id_servicio', $servicioId)->exists()) {
          ServicioEmpresa::where('id', $servicioId)->delete();
        }
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
