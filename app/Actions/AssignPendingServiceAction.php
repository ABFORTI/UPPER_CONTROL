<?php

namespace App\Actions;

use App\Domain\Servicios\PricingService;
use App\Models\Orden;
use App\Models\OTServicio;
use App\Models\ServicioEmpresa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignPendingServiceAction
{
    public function __construct(
        private PricingService $pricing,
    ) {}

    /**
     * Asigna un servicio real a un ítem OT que estaba pendiente.
     *
     * @throws ValidationException|\RuntimeException
     */
    public function execute(Orden $orden, OTServicio $otServicio, int $servicioId, User $user): OTServicio
    {
        // 1. Verificar que el otServicio pertenece a la OT
        if ((int) $otServicio->ot_id !== (int) $orden->id) {
            throw ValidationException::withMessages([
                'ot_servicio' => 'El ítem no pertenece a esta Orden de Trabajo.',
            ]);
        }

        // 2. Verificar que está pendiente y no bloqueado
        if (!$otServicio->canAssignService()) {
            throw ValidationException::withMessages([
                'service_id' => $otServicio->isServiceLocked()
                    ? 'Este servicio ya fue asignado y no puede modificarse.'
                    : 'Este ítem ya tiene un servicio asignado.',
            ]);
        }

        // 3. Verificar que el servicio existe y está activo
        $servicio = ServicioEmpresa::find($servicioId);
        if (!$servicio) {
            throw ValidationException::withMessages([
                'service_id' => 'El servicio seleccionado no existe.',
            ]);
        }
        if (isset($servicio->activo) && !$servicio->activo) {
            throw ValidationException::withMessages([
                'service_id' => 'El servicio seleccionado no está activo.',
            ]);
        }

        // 4. Verificar permisos del usuario
        if (!$user->hasAnyRole(['admin', 'coordinador', 'team_leader'])) {
            throw new \RuntimeException('No tienes permisos para asignar servicios.', 403);
        }

        return DB::transaction(function () use ($orden, $otServicio, $servicio, $user) {
            // 5. Calcular precio unitario basado en catálogo
            $centroId = (int) $orden->id_centrotrabajo;
            $precioUnitario = (float) $this->pricing->precioUnitario($centroId, $servicio->id, null);

            // 6. Actualizar OTServicio
            $otServicio->update([
                'servicio_id' => $servicio->id,
                'precio_unitario' => $precioUnitario,
                'subtotal' => round($precioUnitario * (int) $otServicio->cantidad, 2),
                'service_assignment_status' => 'assigned',
                'service_locked' => true,
                'service_assigned_at' => now(),
                'service_assigned_by' => $user->id,
            ]);

            // 7. Actualizar precio en items del servicio
            foreach ($otServicio->items as $item) {
                $item->update([
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => round($precioUnitario * (int) $item->planeado, 2),
                ]);
            }

            // 8. Recalcular totales de la OT
            $orden->recalcTotals();

            // 9. Registrar auditoría
            activity('ordenes')
                ->performedOn($orden)
                ->causedBy($user)
                ->event('assign_pending_service')
                ->withProperties([
                    'ot_servicio_id' => $otServicio->id,
                    'servicio_id' => $servicio->id,
                    'servicio_nombre' => $servicio->nombre,
                    'sku' => $otServicio->sku,
                    'origen' => $otServicio->origen_customs,
                    'pedimento' => $otServicio->pedimento,
                    'cantidad' => $otServicio->cantidad,
                    'precio_unitario' => $precioUnitario,
                    'usuario_rol' => $user->roles->pluck('name')->first(),
                    'valor_anterior' => 'pendiente',
                    'valor_nuevo' => $servicio->nombre,
                ])
                ->log("Servicio '{$servicio->nombre}' asignado al ítem pendiente #{$otServicio->id} en OT #{$orden->id}");

            return $otServicio->fresh(['servicio', 'items', 'assignedBy']);
        });
    }
}
