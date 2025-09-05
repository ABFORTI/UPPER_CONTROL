<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Orden;

class OrdenPolicy
{
    public function viewAny(User $u): bool {
        return $u->hasAnyRole(['admin','coordinador','team_leader','calidad','facturacion','cliente']);
    }

    public function view(User $u, Orden $o): bool {
        if ($u->hasAnyRole(['admin','facturacion'])) return true;
        if ($u->hasRole('cliente')) return $o->solicitud?->id_cliente === $u->id;
        if ($u->hasRole('team_leader')) return $o->team_leader_id === $u->id;
        // resto por centro
        return (int)$u->centro_trabajo_id === (int)$o->id_centrotrabajo;
    }

    // generar OT desde solicitud (coordinador/admin del centro)
    // Cuando autorizas con Orden::class, el método NO recibe un modelo.
    // Los argumentos extra (aquí, $centroId) van después del $user.
    // app/Policies/OrdenPolicy.php
    public function reportarAvance(User $u, Orden $o): bool
    {
        return $u->hasRole('admin') || $o->team_leader_id === $u->id;
    }
    // ya tenías:
    public function createFromSolicitud(User $u, int $centroId): bool
    {
        return $u->hasRole('admin') ||
            ($u->hasRole('coordinador') && (int)$u->centro_trabajo_id === $centroId);
    }


    // calidad (solo calidad/admin del mismo centro)
    public function calidad(User $u, Orden $o): bool {
        return $u->hasRole('admin') ||
               ($u->hasRole('calidad') && (int)$u->centro_trabajo_id === (int)$o->id_centrotrabajo);
    }

    // cliente autoriza
    public function autorizarCliente(User $u, Orden $o): bool {
        return $u->hasRole('admin') || $o->solicitud?->id_cliente === $u->id;
    }
}
