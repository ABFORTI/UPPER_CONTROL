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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

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

    public function autorizarMasivo(Request $req)
    {
        $req->validate([
            'orden_ids'   => ['required', 'array', 'min:1'],
            'orden_ids.*' => ['required', 'integer', 'exists:ordenes_trabajo,id'],
        ]);

        $u = $req->user();

        $autorizadas = [];
        $omitidas    = [];

        DB::transaction(function () use ($req, $u, &$autorizadas, &$omitidas) {
            $ordenes = Orden::with(['solicitud', 'aprobaciones'])
                ->whereIn('id', $req->input('orden_ids'))
                ->get();

            foreach ($ordenes as $orden) {
                // Re-validar con la misma policy que la autorización individual
                try {
                    $this->authorize('autorizarCliente', $orden);
                } catch (\Throwable) {
                    $omitidas[] = ['id' => $orden->id, 'motivo' => 'Sin permiso para autorizar esta OT.'];
                    continue;
                }

                // Re-validar condiciones de negocio (igual que autorizar individual)
                if ((string)$orden->estatus !== 'completada') {
                    $omitidas[] = ['id' => $orden->id, 'motivo' => 'La OT aún no está completada.'];
                    continue;
                }

                if ($orden->calidad_resultado !== 'validado') {
                    $omitidas[] = ['id' => $orden->id, 'motivo' => 'Aún no está validada por Calidad.'];
                    continue;
                }

                if (!empty($orden->cliente_autorizada_at)) {
                    $omitidas[] = ['id' => $orden->id, 'motivo' => 'Ya fue autorizada por cliente.'];
                    continue;
                }

                $yaAutorizada = $orden->aprobaciones
                    ->where('tipo', 'cliente')
                    ->whereIn('resultado', ['aprobado', 'autorizado'])
                    ->isNotEmpty();

                if ($yaAutorizada) {
                    $omitidas[] = ['id' => $orden->id, 'motivo' => 'Ya fue autorizada por cliente.'];
                    continue;
                }

                // Autorizar
                Aprobacion::create([
                    'aprobable_type' => Orden::class,
                    'aprobable_id'   => $orden->id,
                    'tipo'           => 'cliente',
                    'resultado'      => 'aprobado',
                    'id_usuario'     => $u->id,
                ]);
                $orden->estatus = 'autorizada_cliente';
                $orden->cliente_autorizada_at = now();
                $orden->save();

                $autorizadas[] = $orden->id;

                // Notificar a facturación
                $factUsers = Notify::usersByRoleAndCenter('facturacion', $orden->id_centrotrabajo);
                Notify::send($factUsers, new OtAutorizadaParaFacturacion($orden));

                // Registro de auditoría
                $this->act('ordenes')
                    ->performedOn($orden)
                    ->event('cliente_autoriza_masivo')
                    ->causedBy($u)
                    ->withProperties([
                        'authorized_by_user_id'   => (int)$u->id,
                        'authorized_by_user_name' => (string)($u->name ?? ''),
                        'authorized_by_roles'     => method_exists($u, 'roles') ? $u->roles->pluck('name')->values()->all() : [],
                        'bulk'                    => true,
                    ])
                    ->log("OT #{$orden->id} autorizada masivamente por cliente por {$u->name}");
            }
        });

        $totalAutorizadas = count($autorizadas);
        $totalOmitidas    = count($omitidas);

        if ($totalAutorizadas === 0) {
            $mensaje = "No se autorizó ninguna OT. " .
                ($totalOmitidas > 0 ? "Se omitieron {$totalOmitidas} OT(s)." : '');
            return back()
                ->with('error', trim($mensaje))
                ->with('bulk_result', ['autorizadas' => $autorizadas, 'omitidas' => $omitidas]);
        }

        $mensaje = "Se autorizaron {$totalAutorizadas} OT(s) correctamente.";
        if ($totalOmitidas > 0) {
            $mensaje .= " Se omitieron {$totalOmitidas} OT(s) porque no cumplían las condiciones.";
        }

        return back()
            ->with('ok', $mensaje)
            ->with('bulk_result', ['autorizadas' => $autorizadas, 'omitidas' => $omitidas]);
    }

    public function solicitarRevision(Request $req, Orden $orden)
    {
        $this->authorize('autorizarCliente', $orden);

        $u = $req->user();
        $centro = $u?->centro;
        if (!$centro || !$centro->hasFeature('revision_cliente_no_autoriza')) {
            abort(403, 'No tienes acceso a esta funcionalidad.');
        }

        if ((string)$orden->estatus !== 'completada') {
            abort(422, 'La OT aún no está completada.');
        }

        if ($orden->calidad_resultado !== 'validado') {
            abort(422, 'Aún no está validada por Calidad.');
        }

        if (!empty($orden->cliente_autorizada_at) || (string)$orden->estatus === 'autorizada_cliente') {
            abort(422, 'La OT ya fue autorizada por cliente.');
        }

        $data = $req->validate([
            'comentario' => ['required', 'string', 'min:10', 'max:2000'],
            'fotos' => ['nullable', 'array', 'max:8'],
            'fotos.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        DB::transaction(function () use ($orden, $u, $data) {
            $aprobacion = Aprobacion::create([
                'aprobable_type' => Orden::class,
                'aprobable_id'   => $orden->id,
                'tipo'           => 'cliente',
                'resultado'      => 'rechazado',
                'observaciones'  => trim((string) $data['comentario']),
                'id_usuario'     => $u->id,
            ]);

            foreach (($data['fotos'] ?? []) as $archivo) {
                $storedPath = $archivo->store("cliente-revisiones/orden-{$orden->id}", 'public');
                $orden->archivos()->create([
                    'path'            => $storedPath,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mime'            => $archivo->getClientMimeType(),
                    'size'            => $archivo->getSize(),
                    'subtipo'         => 'revision_cliente:' . $aprobacion->id,
                ]);
            }

            $this->act('ordenes')
                ->performedOn($orden)
                ->event('cliente_solicita_revision')
                ->causedBy($u)
                ->withProperties([
                    'revision_id' => (int) $aprobacion->id,
                    'comentario'  => (string) $aprobacion->observaciones,
                    'adjuntos'    => count($data['fotos'] ?? []),
                ])
                ->log("OT #{$orden->id}: cliente solicitó revisión");
        });

        $comentarioCorto = Str::limit(trim((string) $data['comentario']), 120);
        Notifier::toRoleInCentro(
            'coordinador',
            (int) $orden->id_centrotrabajo,
            'Revisión solicitada por cliente',
            "OT #{$orden->id}: {$comentarioCorto}",
            route('ordenes.show', $orden)
        );

        return back()->with('ok', 'Se envió la revisión al coordinador.');
    }
}
