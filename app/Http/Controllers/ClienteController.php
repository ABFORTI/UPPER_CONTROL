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

        if ((string)$orden->estatus !== 'completada') {
            abort(422, 'La OT aún no está completada.');
        }

        if ($orden->calidad_resultado !== 'validado') abort(422,'Aún no está validada por Calidad.');

        if (!empty($orden->cliente_autorizada_at) || (string)$orden->estatus === 'autorizada_cliente') {
            abort(422, 'La OT ya fue autorizada por cliente.');
        }

        $yaAutorizada = $orden->aprobaciones()
            ->where('tipo', 'cliente')
            ->whereIn('resultado', ['aprobado', 'autorizado'])
            ->exists();
        if ($yaAutorizada) {
            abort(422, 'La OT ya fue autorizada por cliente.');
        }

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
            ->causedBy($u)
            ->withProperties([
                'authorized_by_user_id' => (int)$u->id,
                'authorized_by_user_name' => (string)($u->name ?? ''),
                'authorized_by_roles' => method_exists($u, 'roles') ? $u->roles->pluck('name')->values()->all() : [],
            ])
            ->log("OT #{$orden->id} autorizada por cliente por {$u->name}");

        return back()->with('ok','Has autorizado la OT.');
    }
}
