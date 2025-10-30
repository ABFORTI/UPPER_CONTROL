<?php

// app/Http/Controllers/CalidadController.php
namespace App\Http\Controllers;

use App\Models\Orden;
use App\Models\Aprobacion;
use App\Notifications\CalidadResultadoNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\Notifier;
use Illuminate\Support\Facades\Auth;

class CalidadController extends Controller
{
  // pantalla simple para validar/rechazar
  public function show(Orden $orden) {
    $this->authorize('calidad', $orden);
    $this->authCalidad($orden);
    if ($orden->estatus !== 'completada') abort(422,'La OT aún no está completada.');
    return \Inertia\Inertia::render('Calidad/Review', [
      'orden' => $orden->load('solicitud.cliente','centro','servicio','items'),
      'urls'  => [
        'validar'  => route('calidad.validar', $orden),
        'rechazar' => route('calidad.rechazar', $orden),
      ],
    ]);
  }

  private function act(string $log)
  {
      return app(\Spatie\Activitylog\ActivityLogger::class)->useLog($log);
  }

  public function validar(Request $req, Orden $orden)
  {
    $this->authorize('calidad', $orden);
    $this->authCalidad($orden);
    if ($orden->estatus !== 'completada') abort(422);
    // Regla: si el servicio usa tamaños y aún no hay desglose en la solicitud, no permitir validar
    try {
      $orden->loadMissing(['servicio','solicitud.tamanos']);
      $usaTamanos = (bool)($orden->servicio?->usa_tamanos ?? false);
      $faltaDesglose = $usaTamanos && ($orden->solicitud && $orden->solicitud->tamanos()->count() === 0);
      if ($faltaDesglose) {
        abort(422, 'Debe definir el desglose por tamaños antes de validar calidad.');
      }
    } catch (\Throwable $e) {
      // En caso de error en relaciones, aplicar un fallback conservador
      abort(422, 'Debe definir el desglose por tamaños antes de validar calidad.');
    }
    Aprobacion::create([
      'aprobable_type'=> Orden::class,
      'aprobable_id'  => $orden->id,
      'tipo'          => 'calidad',
      'resultado'     => 'aprobado',
      'observaciones' => $req->input('observaciones'),
      'id_usuario'    => $req->user()->id,
    ]);
    // Al validar, limpiar el motivo y las acciones correctivas para que el mensaje
    // de rechazo desaparezca de la vista de la OT.
    DB::transaction(function() use ($orden) {
      $orden->calidad_resultado = 'validado';
      $orden->motivo_rechazo = null;
      $orden->acciones_correctivas = null;
      $orden->save();
    });

  // Notificar al cliente (dueño de la solicitud)
  optional($orden->solicitud?->cliente)->notify(new \App\Notifications\OtValidadaParaCliente($orden));
  
    $this->act('ordenes')
      ->performedOn($orden)
      ->event('calidad_validar')
      ->log("OT #{$orden->id} validada por calidad");
    // Encolar PDF al validar
    \App\Jobs\GenerateOrdenPdf::dispatch($orden->id);
    return redirect()->route('ordenes.show',$orden->id)->with('ok','Calidad validó la OT');
  }

  public function rechazar(Request $req, Orden $orden)
  {
    $this->authorize('calidad', $orden);
    $this->authCalidad($orden);
    if ($orden->estatus !== 'completada') abort(422);
    $req->validate([
      'observaciones' => ['required','string','max:2000'],
      'acciones_correctivas' => ['nullable','string','max:4000'],
    ]);

    Aprobacion::create([
      'aprobable_type'=> Orden::class,
      'aprobable_id'  => $orden->id,
      'tipo'          => 'calidad',
      'resultado'     => 'rechazado',
      'observaciones' => $req->observaciones,
      'id_usuario'    => $req->user()->id,
    ]);

    // Reset progress so the OT can be corrected and worked again:
    // - delete avances (work reports)
    // - reset cantidad_real and subtotal on orden_items
    // - reset orden totals and mark as en_progreso so team can continue work
    DB::transaction(function() use ($orden, $req) {
  // Keep historical avances: do NOT delete them so history remains available for audit.

      // Reset each item
      foreach ($orden->items as $it) {
        $it->cantidad_real = 0;
        $it->subtotal = 0;
        $it->save();
      }

    // Update orden totals and calidad status
    $orden->total_real = 0;
    $orden->calidad_resultado = 'rechazado';
    $orden->motivo_rechazo = $req->observaciones;
    $orden->acciones_correctivas = $req->input('acciones_correctivas');
    // Move back to en_proceso so TL can re-open work (if previously completed)
    $orden->estatus = 'en_proceso';
    // Limpiar fecha_completada para indicar que la OT ya no está completada
    $orden->fecha_completada = null;
    $orden->save();
    });
    // Registrar un avance informativo en el historial con motivo y acciones
    $coment = '[RECHAZO CALIDAD] ' . $req->observaciones;
    if ($req->filled('acciones_correctivas')) {
      $coment .= ' | Acciones: ' . $req->input('acciones_correctivas');
    }
    \App\Models\Avance::create([
      'id_orden' => $orden->id,
      'id_item' => null,
      'id_usuario' => $req->user()->id,
      'cantidad' => 0,
      'comentario' => $coment,
      'es_corregido' => 0,
    ]);
  // Notificar a cliente (y/o TL/Coordinador) si quieres:
  $cliente = $orden->solicitud->cliente;
  Notification::send($cliente, new CalidadResultadoNotification($orden, 'RECHAZADO', $req->observaciones));
  Notifier::toUser(
    $orden->solicitud->id_cliente,
    'Calidad rechazada',
    "La OT #{$orden->id} fue rechazada por calidad.",
    route('ordenes.show',$orden->id)
  );
    $this->act('ordenes')
      ->performedOn($orden)
      ->event('calidad_rechazar')
      ->withProperties(['observaciones' => $req->observaciones])
      ->log("OT #{$orden->id} rechazada por calidad");
    return redirect()->route('ordenes.show',$orden->id)->with('ok','Calidad rechazó la OT');
  }

  private function authCalidad(Orden $orden): void {
    $u = Auth::user();
    /** @var \App\Models\User $u */
    if ($u->hasRole('admin')) return;
    if (!$u->hasRole('calidad')) abort(403);
    $ids = $this->allowedCentroIds($u);
    if (!in_array((int)$orden->id_centrotrabajo, array_map('intval', $ids), true)) abort(403);
  }
  // app/Http/Controllers/CalidadController.php
  public function index(\Illuminate\Http\Request $req) {
  $u = $req->user();
  $estado = $req->string('estado')->toString(); // 'todos' | pendiente | validado | rechazado | ''
  // Aceptar 'todos' como "sin filtro"; si viene vacío o distinto, usar 'todos' por defecto
  $estadoNorm = in_array($estado, ['pendiente','validado','rechazado','todos'], true)
    ? $estado
    : 'todos';

  $filters = [
    'estado' => $estadoNorm,
    'centro' => $req->integer('centro') ?: null,
    'year' => $req->integer('year') ?: null,
    'week' => $req->integer('week') ?: null,
  ];

  $q = \App\Models\Orden::with('servicio','centro','area','solicitud.marca','teamLeader')
    ->when(!$u->hasRole('admin'), function($qq) use ($u) {
      $ids = $this->allowedCentroIds($u);
      if (!empty($ids)) { $qq->whereIn('id_centrotrabajo', $ids); }
      else { $qq->whereRaw('1=0'); }
    })
    ->when($filters['centro'] !== null, function($qq) use ($u, $filters) {
      if ($u->hasRole('admin')) { $qq->where('id_centrotrabajo', $filters['centro']); }
      else {
        $ids = $this->allowedCentroIds($u);
        if (in_array((int)$filters['centro'], array_map('intval',$ids), true)) { $qq->where('id_centrotrabajo', $filters['centro']); }
      }
    })
    // Lógica de filtrado:
    // - Pendientes: sólo OTs completadas y calidad pendiente
    // - Validadas/Rechazadas: mostrar independientemente de si pasaron a 'autorizada_cliente'
    ->when($estadoNorm === 'pendiente', function($qq){
      $qq->where('estatus','completada')->where('calidad_resultado','pendiente');
    })
    ->when($estadoNorm !== 'pendiente' && $estadoNorm !== 'todos', function($qq) use ($estadoNorm){
      $qq->where('calidad_resultado',$estadoNorm);
    })
    // Filtros de año y semana
    ->when($filters['year'] && $filters['week'], function($qq) use ($filters) {
      $qq->whereRaw('YEAR(created_at) = ? AND WEEK(created_at, 1) = ?', [$filters['year'], $filters['week']]);
    })
    ->when($filters['year'] && !$filters['week'], function($qq) use ($filters) {
      $qq->whereYear('created_at', $filters['year']);
    })
    ->orderByDesc('id');

  $data = $q->paginate(10)->withQueryString();
  $data->getCollection()->transform(function($o){
    $raw = $o->getRawOriginal('created_at');
    $fecha = null; $fechaIso = null;
    if ($raw) {
      try {
        $dt = \Carbon\Carbon::parse($raw);
        $fecha = $dt->format('Y-m-d H:i');
        $fechaIso = $dt->toIso8601String();
      } catch (\Throwable $e) {
        $fecha = substr($raw,0,16);
      }
    }
    return [
      'id' => $o->id,
      'servicio' => ['nombre' => $o->servicio?->nombre],
      'centro'   => ['nombre' => $o->centro?->nombre],
      'producto' => $o->descripcion_general ?: ($o->solicitud?->descripcion ?? null),
      'area'     => ['nombre' => $o->area?->nombre],
      'marca'    => ['nombre' => optional($o->solicitud?->marca)->nombre],
      'team_leader' => ['name' => $o->teamLeader?->name],
      'estatus'  => $o->estatus,
      'calidad_resultado' => $o->calidad_resultado,
      'fecha' => $fecha,
      'fecha_iso' => $fechaIso,
      'created_at_raw' => $raw,
      'urls' => [
        'review' => route('calidad.show', $o),
        'show'   => route('ordenes.show', $o),
      ],
    ];
  });

  // Centros para selector
  $centrosLista = $u->hasRole('admin')
    ? \App\Models\CentroTrabajo::select('id','nombre')->orderBy('nombre')->get()
    : \App\Models\CentroTrabajo::whereIn('id', $this->allowedCentroIds($u))->select('id','nombre')->orderBy('nombre')->get();

  return \Inertia\Inertia::render('Calidad/Index', [
    'data'    => $data,
    'filters' => $filters,
    'urls'    => [ 'index' => route('calidad.index') ],
    'centros' => $centrosLista,
  ]);
  }

  private function allowedCentroIds(\App\Models\User $u): array
  {
    if ($u->hasRole('admin')) return [];
    $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v)=>(int)$v)->all();
    $primary = (int)($u->centro_trabajo_id ?? 0);
    if ($primary) $ids[] = $primary;
    return array_values(array_unique(array_filter($ids)));
  }

}
