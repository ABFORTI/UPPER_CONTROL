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

  // notificar al cliente (dueño de la solicitud)
  optional($orden->solicitud?->cliente)->notify(new \App\Notifications\OtValidadaParaCliente($orden));
  Notifier::toUser(
    $orden->solicitud->id_cliente,
    'Calidad validada',
    "La OT #{$orden->id} fue validada. Autoriza para facturación.",
    route('ordenes.show',$orden->id)
  );
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
    $u = auth()->user();
    if ($u->hasRole('admin')) return;
    if (!$u->hasRole('calidad')) abort(403);
    if ((int)$u->centro_trabajo_id !== (int)$orden->id_centrotrabajo) abort(403);
  }
  // app/Http/Controllers/CalidadController.php
  public function index(\Illuminate\Http\Request $req) {
    $u = $req->user();
    $q = \App\Models\Orden::with('servicio','centro')
        ->when(!$u->hasRole('admin'), fn($qq)=>$qq->where('id_centrotrabajo',$u->centro_trabajo_id))
        ->where('estatus','completada')
        ->where('calidad_resultado','pendiente')
        ->orderByDesc('id')->paginate(10)->withQueryString();

    return \Inertia\Inertia::render('Calidad/Index', ['data'=>$q]);
  }

}
