<?php

// app/Actions/ValidarOrdenCalidadAction.php
namespace App\Actions;

use App\Models\Aprobacion;
use App\Models\Avance;
use App\Models\Orden;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ValidarOrdenCalidadAction
{
    /**
     * Validar calidad de una OT completada.
     * Replica la lógica de CalidadController::validar() sin el contexto HTTP.
     *
     * @throws \RuntimeException si la OT no puede validarse
     */
    public function execute(Orden $orden, User $operador, ?string $comentario = null): void
    {
        // ── Guardas de elegibilidad ──────────────────────────────────────────
        if ((string)$orden->estatus !== 'completada') {
            throw new \RuntimeException("OT #{$orden->id}: estatus no es 'completada' ({$orden->estatus}).");
        }

        if ((string)($orden->calidad_resultado ?? '') === 'validado') {
            throw new \RuntimeException("OT #{$orden->id}: calidad ya validada.");
        }

        if ($orden->hasPendingServiceItems()) {
            throw new \RuntimeException("OT #{$orden->id}: hay ítems pendientes de asignación de servicio.");
        }

        // Misma regla que el controlador: si usa_tamanos y no hay desglose, bloquear
        try {
            $orden->loadMissing(['servicio', 'solicitud.tamanos']);
            $usaTamanos = (bool)\App\Models\ServicioCentro::where('id_centrotrabajo', $orden->id_centrotrabajo)
                ->where('id_servicio', $orden->id_servicio)
                ->value('usa_tamanos');

            if ($usaTamanos && $orden->solicitud && $orden->solicitud->tamanos()->count() === 0) {
                throw new \RuntimeException("OT #{$orden->id}: debe definir el desglose por tamaños antes de validar calidad.");
            }
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Si la relación no existe o falla, conservar comportamiento conservador del controlador
            throw new \RuntimeException("OT #{$orden->id}: no se puede validar calidad (verificación de tamaños falló).");
        }

        // ── Transacción ──────────────────────────────────────────────────────
        DB::transaction(function () use ($orden, $operador, $comentario) {
            Aprobacion::create([
                'aprobable_type' => Orden::class,
                'aprobable_id'   => $orden->id,
                'tipo'           => 'calidad',
                'resultado'      => 'aprobado',
                'observaciones'  => $comentario,
                'id_usuario'     => $operador->id,
            ]);

            $orden->calidad_resultado    = 'validado';
            $orden->motivo_rechazo       = null;
            $orden->acciones_correctivas = null;
            $orden->save();

            Avance::create([
                'id_orden'    => $orden->id,
                'id_item'     => null,
                'id_usuario'  => $operador->id,
                'user_id'     => $operador->id,
                'tipo'        => 'CALIDAD_VALIDADA',
                'cantidad'    => 0,
                'comentario'  => $comentario,
                'es_corregido' => 0,
            ]);
        });

        // Notificar clientes gerentes del centro (fuera de transacción)
        try {
            $clientesGerentes = \App\Services\Notifier::clientGerentesByCenter((int)$orden->id_centrotrabajo);
            \App\Services\Notifier::send($clientesGerentes, new \App\Notifications\OtValidadaParaCliente($orden));
        } catch (\Throwable $e) {
            Log::warning('ValidarOrdenCalidadAction: fallo al enviar notificación a cliente (ignorado)', [
                'orden_id' => $orden->id,
                'error'    => $e->getMessage(),
            ]);
        }

        // Activity log
        try {
            app(\Spatie\Activitylog\ActivityLogger::class)
                ->useLog('ordenes')
                ->performedOn($orden)
                ->causedBy($operador)
                ->event('calidad_validar_masivo')
                ->withProperties(['bulk' => true, 'operador_id' => $operador->id])
                ->log("OT #{$orden->id} validada por calidad masivamente");
        } catch (\Throwable $e) {
            Log::warning('ValidarOrdenCalidadAction: fallo al registrar activity log (ignorado)', [
                'orden_id' => $orden->id,
                'error'    => $e->getMessage(),
            ]);
        }

        // PDF (fuera de transacción)
        \App\Jobs\GenerateOrdenPdf::dispatch($orden->id);
    }
}
