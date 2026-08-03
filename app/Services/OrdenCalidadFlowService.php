<?php

namespace App\Services;

use App\Models\Aprobacion;
use App\Models\Avance;
use App\Models\Orden;
use App\Models\User;
use App\Notifications\OtListaParaAutorizacionCliente;
use App\Notifications\OtListaParaCalidad;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\ActivityLogger;

class OrdenCalidadFlowService
{
    public const FEATURE_KEY = 'omitir_calidad_y_enviar_a_cliente';

    public function shouldBypassQuality(Orden $orden): bool
    {
        $orden->loadMissing('centro.features');

        return (bool) ($orden->centro?->hasFeature(self::FEATURE_KEY) ?? false);
    }

    public function applyCompletionRouting(Orden $orden, ?User $operador = null, ?string $comentario = null): bool
    {
        if (!$this->shouldBypassQuality($orden)) {
            $orden->calidad_resultado = 'pendiente';
            return false;
        }

        $mensaje = is_string($comentario) ? trim($comentario) : null;
        if ($mensaje === '') {
            $mensaje = null;
        }

        $orden->calidad_resultado = 'validado';
        $orden->motivo_rechazo = null;
        $orden->acciones_correctivas = null;

        if ($operador) {
            Aprobacion::create([
                'aprobable_type' => Orden::class,
                'aprobable_id'   => $orden->id,
                'tipo'           => 'calidad',
                'resultado'      => 'aprobado',
                'observaciones'  => $mensaje,
                'id_usuario'     => $operador->id,
            ]);

            Avance::create([
                'id_orden'     => $orden->id,
                'id_item'      => null,
                'id_usuario'   => $operador->id,
                'user_id'      => $operador->id,
                'tipo'         => 'CALIDAD_OMITIDA',
                'cantidad'     => 0,
                'comentario'   => $mensaje,
                'es_corregido' => 0,
            ]);

            try {
                app(ActivityLogger::class)
                    ->useLog('ordenes')
                    ->performedOn($orden)
                    ->causedBy($operador)
                    ->event('calidad_omitida')
                    ->withProperties(['feature_key' => self::FEATURE_KEY])
                    ->log("OT #{$orden->id}: calidad omitida por configuración del almacén");
            } catch (\Throwable $e) {
                Log::warning('OrdenCalidadFlowService: fallo al registrar activity log de calidad omitida', [
                    'orden_id' => $orden->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return true;
    }

    public function shouldHideQualityStatusForUser(Orden $orden, ?User $user): bool
    {
        return $this->shouldBypassQuality($orden);
    }

    public function notifyCompletionRouting(Orden $orden): void
    {
        if ($this->shouldBypassQuality($orden)) {
            $clientesGerentes = \App\Support\Notify::clientGerentesByCenter((int) $orden->id_centrotrabajo);
            \App\Support\Notify::send($clientesGerentes, new OtListaParaAutorizacionCliente($orden));
            return;
        }

        $usuariosCalidad = \App\Support\Notify::usersByRoleAndCenter('calidad', (int) $orden->id_centrotrabajo);
        if ($usuariosCalidad->isEmpty()) {
            return;
        }

        try {
            Notification::send($usuariosCalidad, new OtListaParaCalidad($orden));
        } catch (\Throwable $e) {
            Log::warning('OrdenCalidadFlowService: fallo al notificar flujo de calidad', [
                'orden_id' => $orden->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
