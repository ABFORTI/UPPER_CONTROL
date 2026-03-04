<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Solicitud;

class SolicitudPolicy
{
    private function canAdminCentro(User $u, int $centroId): bool
    {
        if ($u->hasRole('superadmin')) {
            return true;
        }

        if (!$u->hasRole('admin')) {
            return false;
        }

        $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v)=>(int)$v)->all();
        $primary = (int)($u->centro_trabajo_id ?? 0);
        if ($primary) {
            $ids[] = $primary;
        }
        $ids = array_values(array_unique($ids));

        return in_array($centroId, $ids, true);
    }

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

    public function delete(User $u, Solicitud $s): bool
    {
        return $this->canAdminCentro($u, (int)$s->id_centrotrabajo);
    }

    public function restore(User $u, Solicitud $s): bool
    {
        return $this->canAdminCentro($u, (int)$s->id_centrotrabajo);
    }

    public function forceDelete(User $u, Solicitud $s): bool
    {
        return $this->canAdminCentro($u, (int)$s->id_centrotrabajo);
    }

    public function cancelar(User $u, Solicitud $s): bool
    {
        return $this->canAdminCentro($u, (int)$s->id_centrotrabajo);
    }
}
