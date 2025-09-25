<?php
// app/Http/Controllers/FacturaController.php
namespace App\Http\Controllers;

use App\Models\Orden;
use App\Models\Factura;
use Illuminate\Http\Request;
use Inertia\Inertia;
use function auth; // para que el analizador reconozca el helper
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Services\Notifier;
use App\Jobs\GenerateFacturaPdf;
use Illuminate\Support\Facades\Storage;

class FacturaController extends Controller
{
  public function index(Request $request)
  {
    $u = $request->user();
    if (!$u->hasRole('admin') && !$u->hasRole('facturacion')) abort(403);

    $estatus = $request->get('estatus'); // opcional

    $q = Factura::query()->with(['orden.servicio','orden.centro']);
    if ($estatus) {
        $q->where('estatus', $estatus);
    }
    // Restricción por centro si no es admin
    if (!$u->hasRole('admin')) {
        $q->whereHas('orden', fn($qq)=>$qq->where('id_centrotrabajo', $u->centro_trabajo_id));
    }

    $facturas = $q->latest('id')->limit(100)->get()->map(function($f){
        return [
          'id' => $f->id,
          'orden_id' => $f->id_orden,
          'servicio' => $f->orden?->servicio?->nombre,
          'centro'   => $f->orden?->centro?->nombre,
          'total'    => $f->total,
          'estatus'  => $f->estatus,
          'folio'    => $f->folio_externo,
          'created_at' => $f->created_at?->toDateTimeString(),
          'url' => route('facturas.show', $f->id),
        ];
    });

    return Inertia::render('Facturas/Index', [
        'items' => $facturas,
        'filtros' => [ 'estatus' => $estatus ],
        'urls' => [ 'base' => route('facturas.index') ],
        'estatuses' => ['pendiente','facturado','por_pagar','pagado'],
    ]);
  }
  
public function pdf(\App\Models\Factura $factura)
{
    $this->authorize('view', $factura->orden);

    if ($factura->pdf_path && Storage::exists($factura->pdf_path)) {
        return Storage::download($factura->pdf_path, "Factura_{$factura->id}.pdf");
    }

    // Fallback on-demand
    $factura->load(['orden.servicio','orden.centro','orden.items']);
    $pdf = PDF::loadView('pdf.factura', ['factura'=>$factura])->setPaper('letter');
    return $pdf->download("Factura_{$factura->id}.pdf");
}
  public function createFromOrden(\App\Models\Orden $orden) {
    $this->authFacturacion($orden);
    if ($orden->estatus !== 'autorizada_cliente') abort(422,'La OT no está autorizada por el cliente.');
    if ($f = \App\Models\Factura::where('id_orden',$orden->id)->first()) {
        return redirect()->route('facturas.show', $f->id);
    }

    return \Inertia\Inertia::render('Facturas/CreateFromOrden', [
        'orden'          => $orden->only('id','total_planeado','id_centrotrabajo'),
        'total_sugerido' => $orden->total_planeado,
        'urls'           => [
            'store' => route('facturas.storeFromOrden', $orden), // <- absoluta
        ],
    ]);
}

  public function storeFromOrden(Request $req, Orden $orden)
    {
        $this->authFacturacion($orden);
        $req->validate([
      'total' => ['required','numeric','min:0'],
      'folio_externo' => ['nullable','string','max:100'],
    ]);
    $factura = Factura::create([
      'id_orden' => $orden->id,
      'total' => $req->total,
      'folio_externo' => $req->folio_externo,
      'estatus' => 'pendiente',
    ]);
    // Notificar al cliente
    Notifier::toUser(
        $orden->solicitud->id_cliente,
        'Factura generada',
        "Se generó la factura de la OT #{$orden->id}.",
        route('facturas.show',$factura->id)
    );
        $this->act('facturas')
            ->performedOn($factura)
            ->event('crear_factura')
            ->withProperties(['orden_id' => $orden->id, 'total' => $factura->total])
            ->log("Factura #{$factura->id} creada para OT #{$orden->id}");
        GenerateFacturaPdf::dispatch($factura->id);
    return redirect()->route('facturas.show',$factura->id)->with('ok','Factura registrada');
  }

  public function show(\App\Models\Factura $factura) {
  $this->authorize('view', $factura);
  $this->authFacturacion($factura->orden);
    $factura->load('orden.servicio','orden.centro','orden.solicitud.cliente');

  return \Inertia\Inertia::render('Facturas/Show', [
    'factura' => $factura->toArray(), // <- objeto plano
    'urls'    => [
      'facturado' => route('facturas.facturado', $factura),
      'cobro'     => route('facturas.cobro',     $factura),
      'pagado'    => route('facturas.pagado',    $factura),
      'pdf'       => route('facturas.pdf', $factura),
    ],
  ]);
}


  public function marcarFacturado(Request $req, Factura $factura) {
    $this->authorize('operar', $factura);
    $this->authFacturacion($factura->orden);
    $req->validate(['folio_externo'=>['required','string','max:100']]);
    $factura->update([
      'estatus'=>'facturado',
      'folio_externo'=>$req->folio_externo,
      'fecha_facturado'=>now()->toDateString(),
    ]);
    // Notificar al cliente
    Notifier::toUser(
        $factura->orden->solicitud->id_cliente,
        'Factura actualizada',
        "La factura #{$factura->id} cambió a estatus: {$factura->estatus}.",
        route('facturas.show',$factura->id)
    );
        $this->act('facturas')
            ->performedOn($factura)
            ->event('estatus')
            ->withProperties(['estatus' => $factura->estatus])
            ->log("Factura #{$factura->id} actualizada a {$factura->estatus}");
        GenerateFacturaPdf::dispatch($factura->id);
    return back()->with('ok','Marcada como facturada');
  }

  public function marcarCobro(Request $req, Factura $factura) {
  $this->authorize('operar', $factura);
  $this->authFacturacion($factura->orden);
  $factura->update(['estatus'=>'por_pagar', 'fecha_cobro'=>now()->toDateString()]);
  // Notificar al cliente
  Notifier::toUser(
    $factura->orden->solicitud->id_cliente,
    'Factura actualizada',
    "La factura #{$factura->id} cambió a estatus: {$factura->estatus}.",
    route('facturas.show',$factura->id)
  );
        $this->act('facturas')
            ->performedOn($factura)
            ->event('estatus')
            ->withProperties(['estatus' => $factura->estatus])
            ->log("Factura #{$factura->id} actualizada a {$factura->estatus}");
        GenerateFacturaPdf::dispatch($factura->id);
  return back()->with('ok','Cobro registrado (por pagar)');
  }

  public function marcarPagado(Request $req, Factura $factura) {
  $this->authorize('operar', $factura);
  $this->authFacturacion($factura->orden);
  $factura->update(['estatus'=>'pagado', 'fecha_pagado'=>now()->toDateString()]);
  // Notificar al cliente
  Notifier::toUser(
    $factura->orden->solicitud->id_cliente,
    'Factura actualizada',
    "La factura #{$factura->id} cambió a estatus: {$factura->estatus}.",
    route('facturas.show',$factura->id)
  );
        $this->act('facturas')
            ->performedOn($factura)
            ->event('estatus')
            ->withProperties(['estatus' => $factura->estatus])
            ->log("Factura #{$factura->id} actualizada a {$factura->estatus}");
        GenerateFacturaPdf::dispatch($factura->id);
  return back()->with('ok','Pago confirmado');
  }

  private function authFacturacion(\App\Models\Orden $orden): void {
    $u = request()->user();
    if ($u->hasRole('admin')) return;
    if (!$u->hasRole('facturacion')) abort(403);
    if ((int)$u->centro_trabajo_id !== (int)$orden->id_centrotrabajo) abort(403);
  }
  private function act(string $log)
  {
      return app(\Spatie\Activitylog\ActivityLogger::class)->useLog($log);
  }
}
