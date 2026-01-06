<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Solicitud;

class SolicitudPolicy
{
    public function viewAny(User $u): bool {
        return $u->hasAnyRole(['admin','Cliente_Supervisor','coordinador','calidad','facturacion','team_leader','gerente_upper']);
    }

    public function view(User $u, Solicitud $s): bool {
        // Gerente Upper: vista total
        if ($u->hasRole('gerente_upper')) return true;
        if ($u->hasAnyRole(['admin','facturacion'])) return true;
        // Cliente con alcance a todo el centro (opcional)
        if ($u->hasRole('Cliente_Gerente')) {
            return (int)$u->centro_trabajo_id === (int)$s->id_centrotrabajo;
        }
        // Supervisor (antes 'cliente'): sólo sus propias solicitudes
        if ($u->hasRole('Cliente_Supervisor')) return $s->id_cliente === $u->id;
        // resto por centro
        return (int)$u->centro_trabajo_id === (int)$s->id_centrotrabajo;
    }

    public function create(User $u): bool {
        return $u->hasAnyRole(['admin','Cliente_Supervisor','Cliente_Gerente']);
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
