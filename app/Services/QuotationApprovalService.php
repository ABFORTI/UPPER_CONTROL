<?php

namespace App\Services;

use App\Events\QuotationApproved;
use App\Models\CentroTrabajo;
use App\Models\Cotizacion;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuotationApprovalService
{
    public function approveSentQuotation(
        Cotizacion $cotizacion,
        ?int $actorClientId = null,
        ?string $via = null,
        array $auditPayload = [],
    ): Cotizacion {
        $expectedSolicitudes = $this->expectedSolicitudesCount($cotizacion);

        DB::transaction(function () use ($cotizacion, $actorClientId, $via, $auditPayload, $expectedSolicitudes) {
            /** @var Cotizacion $locked */
            $locked = Cotizacion::whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();

            if ($locked->estatus !== Cotizacion::ESTATUS_SENT) {
                throw new \RuntimeException('La cotización ya fue procesada o no está disponible.');
            }

            if ($locked->isExpired()) {
                $locked->forceFill(['estatus' => Cotizacion::ESTATUS_EXPIRED])->save();
                throw new \RuntimeException('La cotización expiró.');
            }

            $locked->forceFill([
                'estatus' => Cotizacion::ESTATUS_APPROVED,
                'approved_at' => now(),
                // invalidar token
                'approval_token_hash' => null,
            ])->save();

            // Crear solicitudes dentro de la misma transacción vía evento/listener.
            event(new QuotationApproved((int)$locked->id, $actorClientId));

            // Auditoría (si existe la tabla)
            if (Schema::hasTable('cotizacion_audit_logs')) {
                try {
                    DB::table('cotizacion_audit_logs')->insert([
                        'cotizacion_id' => (int)$locked->id,
                        'action' => 'approved',
                        'actor_user_id' => null,
                        'actor_client_id' => $actorClientId ? (int)$actorClientId : null,
                        'payload' => json_encode(array_merge([
                            'via' => $via,
                            'solicitudes_generadas' => $expectedSolicitudes,
                        ], $auditPayload)),
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    // noop
                }
            }
        });

        $cotizacion->refresh();

        $this->notifyCoordinator($cotizacion);

        return $cotizacion;
    }

    public function generateSolicitudFolio(int $centroId): string
    {
        $centro = CentroTrabajo::find($centroId);
        $prefijo = $centro?->prefijo
            ?? ($centro && $centro->nombre
                ? strtoupper(substr(preg_replace('/[^a-z]/i', '', $centro->nombre), 0, 3))
                : 'UPR');

        $prefijo = strtoupper(substr($prefijo, 0, 10));
        $yyyymm = now()->format('Ym');
        $base = $prefijo . '-' . $yyyymm . '-';

        $lastFolio = Solicitud::where('folio', 'like', $base . '%')
            ->orderByDesc('folio')
            ->lockForUpdate()
            ->value('folio');

        $seq = 1;
        if (is_string($lastFolio) && preg_match('/-(\d{4})$/', $lastFolio, $m)) {
            $seq = ((int)$m[1]) + 1;
        }

        return sprintf('%s%04d', $base, $seq);
    }

    private function expectedSolicitudesCount(Cotizacion $cotizacion): int
    {
        try {
            $cotizacion->loadMissing(['items.servicios']);
            $n = 0;
            foreach ($cotizacion->items as $item) {
                $n += $item->servicios->count();
            }
            return $n;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function notifyCoordinator(Cotizacion $cotizacion): void
    {
        // Notificación al coordinador creador: in-app (database) y opcional email.
        try {
            if (!Schema::hasTable('notifications')) {
                return;
            }

            $coordinator = User::find((int)$cotizacion->created_by);
            if (!$coordinator) {
                return;
            }

            $count = 0;
            if (Schema::hasTable('solicitudes')) {
                $count = Solicitud::where('id_cotizacion', (int)$cotizacion->id)->count();
            }

            $coordinator->notify(new \App\Notifications\QuotationApprovedCoordinatorNotification($cotizacion, $count));
        } catch (\Throwable $e) {
            // noop
        }
    }
}
