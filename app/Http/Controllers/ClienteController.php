<?php

// app/Http/Controllers/ClienteController.php
namespace App\Http\Controllers;

use App\Models\Orden;
use App\Models\Aprobacion;
use App\Notifications\ClienteAutorizoNotification;
use App\Notifications\OtAutorizadaParaFacturacion;
use App\Support\Notify;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ClienteController extends Controller
{
    private function act(string $log)
    {
        return app(\Spatie\Activitylog\ActivityLogger::class)->useLog($log);
    }

    public function autorizar(Request $req, Orden $orden)
    {
        $this->authorize('autorizarCliente', $orden);
    $u = $req->user();
        // Validación reforzada: dueño de la solicitud, admin, o gerente (antes 'cliente_centro') del mismo centro
        $esDueno = (int)($orden->solicitud->id_cliente ?? 0) === (int)$u->id;
        $esClienteCentroMismo = $u->hasRole('gerente') && (int)$u->centro_trabajo_id === (int)$orden->id_centrotrabajo;
        if (!$u->hasRole('admin') && !$esDueno && !$esClienteCentroMismo) abort(403);
        if ($orden->calidad_resultado !== 'validado') abort(422,'Aún no está validada por Calidad.');

        Aprobacion::create([
            'aprobable_type'=> Orden::class,
            'aprobable_id'  => $orden->id,
            'tipo'          => 'cliente',
            'resultado'     => 'aprobado',
            'id_usuario'    => $u->id,
        ]);
        $orden->estatus = 'autorizada_cliente';
        $orden->cliente_autorizada_at = now();
        $orden->save();

        // Avisar a 'facturacion' del centro
    $factUsers = Notify::usersByRoleAndCenter('facturacion', $orden->id_centrotrabajo);
        Notify::send($factUsers, new OtAutorizadaParaFacturacion($orden));

        $this->act('ordenes')
            ->performedOn($orden)
            ->event('cliente_autoriza')
            ->log("OT #{$orden->id} autorizada por cliente");

        return back()->with('ok','Has autorizado la OT.');
    }
}
