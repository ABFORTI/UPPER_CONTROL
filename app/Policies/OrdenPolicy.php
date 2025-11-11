<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Orden;

class OrdenPolicy
{
    public function viewAny(User $u): bool {
        return $u->hasAnyRole(['admin','coordinador','team_leader','calidad','facturacion','cliente','gerente']);
    }

    public function view(User $u, Orden $o): bool {
        // Gerente: vista total sin restricciones por centro
        if ($u->hasRole('gerente')) return true;
        if ($u->hasAnyRole(['admin','facturacion'])) return true;
        // Cliente con alcance a todo el centro (rol opcional 'cliente_centro')
        if ($u->hasRole('cliente_centro')) {
            return (int)$o->id_centrotrabajo === (int)$u->centro_trabajo_id;
        }
        // Cliente estándar: sólo sus propias OTs (las de sus solicitudes)
        if ($u->hasRole('cliente')) return $o->solicitud?->id_cliente === $u->id;
        if ($u->hasRole('team_leader')) return $o->team_leader_id === $u->id;
        // calidad o coordinador: permitir centros asignados (pivot) + principal
        if ($u->hasAnyRole(['calidad','coordinador'])) {
            $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v)=>(int)$v)->all();
            $primary = (int)($u->centro_trabajo_id ?? 0);
            if ($primary) $ids[] = $primary;
            $ids = array_values(array_unique($ids));
            return in_array((int)$o->id_centrotrabajo, $ids, true);
        }
        // resto por centro (solo principal)
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
        if ($u->hasRole('admin')) return true;
        // Gerente: solo lectura (no debe ejecutar acciones de validar/rechazar) -> devolver false aquí
        if ($u->hasRole('gerente')) return false;
        if (!$u->hasRole('calidad')) return false;
        // Permitimos centros asignados por pivot más el centro principal
        $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v)=>(int)$v)->all();
        $primary = (int)($u->centro_trabajo_id ?? 0);
        if ($primary) $ids[] = $primary;
        $ids = array_values(array_unique($ids));
        return in_array((int)$o->id_centrotrabajo, $ids, true);
    }

    // cliente autoriza
    public function autorizarCliente(User $u, Orden $o): bool {
        return $u->hasRole('admin') || $o->solicitud?->id_cliente === $u->id;
    }
}
