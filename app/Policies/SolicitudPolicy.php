<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Solicitud;

class SolicitudPolicy
{
    public function viewAny(User $u): bool {
        return $u->hasAnyRole(['admin','cliente','coordinador','calidad','facturacion','team_leader']);
    }

    public function view(User $u, Solicitud $s): bool {
        if ($u->hasAnyRole(['admin','facturacion'])) return true;
        // Cliente con alcance a todo el centro (opcional)
        if ($u->hasRole('cliente_centro')) {
            return (int)$u->centro_trabajo_id === (int)$s->id_centrotrabajo;
        }
        // Cliente estándar: sólo sus propias solicitudes
        if ($u->hasRole('cliente')) return $s->id_cliente === $u->id;
        // resto por centro
        return (int)$u->centro_trabajo_id === (int)$s->id_centrotrabajo;
    }

    public function create(User $u): bool {
        return $u->hasAnyRole(['admin','cliente']);
    }

    // aprobar / rechazar por coordinador o admin del mismo centro
    public function aprobar(User $u, Solicitud $s): bool {
        return $u->hasRole('admin') ||
               ($u->hasRole('coordinador') && (int)$u->centro_trabajo_id === (int)$s->id_centrotrabajo);
    }
    public function rechazar(User $u, Solicitud $s): bool {
        return $this->aprobar($u,$s);
    }
}
