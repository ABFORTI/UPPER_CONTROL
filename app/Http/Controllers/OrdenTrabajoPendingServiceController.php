<?php

namespace App\Http\Controllers;

use App\Actions\AssignPendingServiceAction;
use App\Http\Requests\AssignPendingServiceRequest;
use App\Models\Orden;
use App\Models\OTServicio;

class OrdenTrabajoPendingServiceController extends Controller
{
    /**
     * Asigna un servicio real a un ítem pendiente de la OT.
     * Solo admin, coordinador, team_leader.
     */
    public function assign(
        AssignPendingServiceRequest $request,
        Orden $orden,
        OTServicio $otServicio,
        AssignPendingServiceAction $action,
    ) {
        $this->authorize('assignPendingService', $orden);

        $action->execute(
            $orden,
            $otServicio,
            (int) $request->validated('service_id'),
            $request->user(),
        );

        return back()->with('ok', 'Servicio asignado correctamente. El precio ha sido calculado.');
    }
}
