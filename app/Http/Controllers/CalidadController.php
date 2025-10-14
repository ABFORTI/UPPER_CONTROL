<?php

// app/Http/Controllers/CalidadController.php
namespace App\Http\Controllers;

use App\Models\Orden;
use App\Models\Aprobacion;
use App\Notifications\CalidadResultadoNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
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
    Aprobacion::create([
      'aprobable_type'=> Orden::class,
      'aprobable_id'  => $orden->id,
      'tipo'          => 'calidad',
      'resultado'     => 'aprobado',
      'observaciones' => $req->input('observaciones'),
      'id_usuario'    => $req->user()->id,
    ]);
    $orden->calidad_resultado = 'validado';
    $orden->save();

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
    $req->validate(['observaciones'=>['required','string','max:2000']]);
    Aprobacion::create([
      'aprobable_type'=> Orden::class,
      'aprobable_id'  => $orden->id,
      'tipo'          => 'calidad',
      'resultado'     => 'rechazado',
      'observaciones' => $req->observaciones,
      'id_usuario'    => $req->user()->id,
    ]);
  $orden->update(['calidad_resultado'=>'rechazado']);
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
    'year' => $req->integer('year') ?: null,
    'week' => $req->integer('week') ?: null,
  ];

  $q = \App\Models\Orden::with('servicio','centro')
    ->when(!$u->hasRole('admin'), function($qq) use ($u) {
      $ids = $this->allowedCentroIds($u);
      if (!empty($ids)) { $qq->whereIn('id_centrotrabajo', $ids); }
      else { $qq->whereRaw('1=0'); }
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
    return [
      'id' => $o->id,
      'servicio' => ['nombre' => $o->servicio?->nombre],
      'centro'   => ['nombre' => $o->centro?->nombre],
      'estatus'  => $o->estatus,
      'calidad_resultado' => $o->calidad_resultado,
      'created_at' => $o->created_at,
      'urls' => [
        'review' => route('calidad.show', $o),
        'show'   => route('ordenes.show', $o),
      ],
    ];
  });

  return \Inertia\Inertia::render('Calidad/Index', [
    'data'    => $data,
    'filters' => $filters,
    'urls'    => [ 'index' => route('calidad.index') ],
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
