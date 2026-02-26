<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Orden;
use Illuminate\Support\Facades\Log;

class OrdenPolicy
{
    public function viewAny(User $u): bool {
        return $u->hasAnyRole(['admin','coordinador','team_leader','calidad','facturacion','Cliente_Supervisor','gerente_upper']);
    }

    public function view(User $u, Orden $o): bool {
        // Contexto para diagnóstico
        $ctx = [
            'orden_id' => $o->id,
            'orden_centro' => (int)$o->id_centrotrabajo,
            'solicitud_id' => (int)($o->id_solicitud ?? 0),
            'solicitud_cliente' => (int)($o->solicitud?->id_cliente ?? 0),
            'user_id' => $u->id,
            'user_roles' => method_exists($u, 'roles') ? $u->roles->pluck('name')->all() : [],
            'user_centro' => (int)($u->centro_trabajo_id ?? 0),
        ];

        // Prioridades de autorización:
        // 1) Admin/Facturación: acceso total
        if ($u->hasAnyRole(['admin','facturacion'])) {
            return true;
        }

        // 2) Dueño de la solicitud (cliente propietario) SIEMPRE puede ver, incluso si también tiene rol gerente
        $isOwner = (int)($o->solicitud?->id_cliente ?? 0) === (int)$u->id;
        if ($isOwner) {
            return true;
        }

        $allowed = false;
        $checkedAnyScope = false;

        // 3) Gerente Upper: puede ver si la OT pertenece a alguno de sus centros asignados (principal + pivots)
        if ($u->hasRole('gerente_upper')) {
            $checkedAnyScope = true;
            $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v)=>(int)$v)->all();
            $primary = (int)($u->centro_trabajo_id ?? 0);
            if ($primary) $ids[] = $primary;
            $ids = array_values(array_unique($ids));
            $allowed = $allowed || in_array((int)$o->id_centrotrabajo, $ids, true);
        }

        // Cliente con alcance a centro completo
        if ($u->hasRole('Cliente_Gerente')) {
            $checkedAnyScope = true;
            $allowed = $allowed || ((int)$o->id_centrotrabajo === (int)$u->centro_trabajo_id);
        }

        // Supervisor: sólo sus propias OTs
        if ($u->hasRole('Cliente_Supervisor')) {
            $checkedAnyScope = true;
            $allowed = $allowed || $isOwner;
        }

        // Team Leader: sólo OTs asignadas (pero si además tiene roles más amplios, se suman abajo)
        if ($u->hasRole('team_leader')) {
            $checkedAnyScope = true;
            $allowed = $allowed || ((int)$o->team_leader_id === (int)$u->id);
        }

        // Calidad o coordinador: permitir centros asignados (pivot) + principal
        if ($u->hasAnyRole(['calidad','coordinador'])) {
            $checkedAnyScope = true;
            $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v)=>(int)$v)->all();
            $primary = (int)($u->centro_trabajo_id ?? 0);
            if ($primary) $ids[] = $primary;
            $ids = array_values(array_unique($ids));
            $allowed = $allowed || in_array((int)$o->id_centrotrabajo, $ids, true);
        }

        // Resto (sin roles con alcance explícito): por centro principal
        if (!$checkedAnyScope) {
            $allowed = (int)$u->centro_trabajo_id === (int)$o->id_centrotrabajo;
        }

        if (!$allowed) {
            Log::warning('OrdenPolicy.view DENY', $ctx);
        }
        return $allowed;
    }

    // generar OT desde solicitud (coordinador/admin del centro)
    // Cuando autorizas con Orden::class, el método NO recibe un modelo.
    // Los argumentos extra (aquí, $centroId) van después del $user.
    // app/Policies/OrdenPolicy.php
    public function reportarAvance(User $u, Orden $o): bool
    {
        // Si la OT ya fue cortada, se cierra producción para esta OT origen
        // (ot_status: partial/closed). También bloquear si ya está en flujo posterior.
        if (in_array((string)($o->ot_status ?? 'active'), ['partial', 'closed', 'canceled'], true)) {
            return false;
        }
        if (in_array((string)$o->estatus, ['completada', 'autorizada_cliente', 'facturada', 'entregada'], true)) {
            return false;
        }

        if ($u->hasRole('admin')) return true;

        // Sumar permisos entre roles
        $allowed = false;

        if ($u->hasRole('team_leader')) {
            $allowed = $allowed || ((int)$o->team_leader_id === (int)$u->id);
        }

        if ($u->hasRole('coordinador')) {
            $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v)=>(int)$v)->all();
            $primary = (int)($u->centro_trabajo_id ?? 0);
            if ($primary) $ids[] = $primary;
            $ids = array_values(array_unique($ids));
            $allowed = $allowed || in_array((int)$o->id_centrotrabajo, $ids, true);
        }

        return $allowed;
    }

    public function registrarAjuste(User $u, Orden $o): bool
    {
        if ($u->hasRole('admin')) return true;

        $allowed = false;

        if ($u->hasRole('team_leader')) {
            $allowed = $allowed || ((int)$o->team_leader_id === (int)$u->id);
        }

        if ($u->hasRole('coordinador')) {
            $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v)=>(int)$v)->all();
            $primary = (int)($u->centro_trabajo_id ?? 0);
            if ($primary) $ids[] = $primary;
            $ids = array_values(array_unique($ids));
            $allowed = $allowed || in_array((int)$o->id_centrotrabajo, $ids, true);
        }

        return $allowed;
    }
    // ya tenías:
    public function createFromSolicitud(User $u, int $centroId): bool
    {
        return $u->hasRole('admin') ||
            ($u->hasRole('coordinador') && (int)$u->centro_trabajo_id === $centroId);
    }

    // Definir desglose por tamaños (coordinador, admin o team_leader asignado)
    public function definirTamanos(User $u, Orden $o): bool
    {
        if ($u->hasRole('admin')) return true;
        if ($u->hasRole('coordinador') && (int)$u->centro_trabajo_id === (int)$o->id_centrotrabajo) return true;
        // Team leader asignado a la OT también puede definir tamaños
        if ($u->hasRole('team_leader') && (int)$o->team_leader_id === (int)$u->id) return true;
        return false;
    }


    // calidad (solo calidad/admin del mismo centro)
    public function calidad(User $u, Orden $o): bool {
        if ($u->hasRole('admin')) return true;
        // Gerente Upper: solo lectura (no debe ejecutar acciones de validar/rechazar) -> devolver false aquí
        if ($u->hasRole('gerente_upper')) return false;
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
        // Admin siempre
        if ($u->hasRole('admin')) return true;
        // Dueño de la solicitud
        if ((int)($o->solicitud?->id_cliente ?? 0) === (int)$u->id) return true;
        // Cliente con alcance a centro completo puede autorizar del mismo centro
        if ($u->hasRole('Cliente_Gerente') && (int)$u->centro_trabajo_id === (int)$o->id_centrotrabajo) return true;
        return false;
    }
}
