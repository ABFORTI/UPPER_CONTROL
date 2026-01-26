<?php

namespace App\Policies;

use App\Models\Cotizacion;
use App\Models\User;

class CotizacionPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->hasAnyRole([
            'admin',
            'coordinador',
            'Cliente_Supervisor',
            'Cliente_Gerente',
            'gerente_upper',
            'facturacion',
        ]);
    }

    public function view(User $u, Cotizacion $c): bool
    {
        if ($u->hasRole('admin')) return true;

        // Creador siempre puede ver
        if ((int)$c->created_by === (int)$u->id) return true;

        // Gerente Upper: vista (solo lectura) en sus centros asignados
        if ($u->hasRole('gerente_upper')) {
            $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v) => (int)$v)->all();
            $primary = (int)($u->centro_trabajo_id ?? 0);
            if ($primary) $ids[] = $primary;
            $ids = array_values(array_unique($ids));
            return in_array((int)$c->id_centrotrabajo, $ids, true);
        }

        // Cliente supervisor: solo sus cotizaciones
        if ($u->hasRole('Cliente_Supervisor')) {
            return (int)$c->id_cliente === (int)$u->id;
        }

        // Cliente gerente: por centro
        if ($u->hasRole('Cliente_Gerente')) {
            return (int)$c->id_centrotrabajo === (int)$u->centro_trabajo_id;
        }

        // Coordinador: por centro principal
        if ($u->hasRole('coordinador')) {
            return (int)$c->id_centrotrabajo === (int)$u->centro_trabajo_id;
        }

        // Facturación: por centro asignado (solo lectura)
        if ($u->hasRole('facturacion')) {
            $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v) => (int)$v)->all();
            $primary = (int)($u->centro_trabajo_id ?? 0);
            if ($primary) $ids[] = $primary;
            $ids = array_values(array_unique($ids));
            return in_array((int)$c->id_centrotrabajo, $ids, true);
        }

        return false;
    }

    public function create(User $u): bool
    {
        return $u->hasAnyRole(['admin', 'coordinador']);
    }

    public function update(User $u, Cotizacion $c): bool
    {
        if ($u->hasRole('admin')) return true;
        if (!$u->hasRole('coordinador')) return false;

        return (int)$c->created_by === (int)$u->id
            && (int)$c->id_centrotrabajo === (int)$u->centro_trabajo_id;
    }

    public function send(User $u, Cotizacion $c): bool
    {
        if ($u->hasRole('admin')) return true;
        if (!$u->hasRole('coordinador')) return false;
        return (int)$c->created_by === (int)$u->id && (int)$c->id_centrotrabajo === (int)$u->centro_trabajo_id;
    }

    public function cancel(User $u, Cotizacion $c): bool
    {
        return $this->send($u, $c);
    }

    public function approve(User $u, Cotizacion $c): bool
    {
        // gerente_upper solo lectura
        if ($u->hasRole('gerente_upper')) return false;

        if ($u->hasRole('admin')) return true;

        if ((int)$c->id_cliente === (int)$u->id) return true;

        if ($u->hasRole('Cliente_Gerente')) {
            return (int)$c->id_centrotrabajo === (int)$u->centro_trabajo_id;
        }

        return false;
    }

    public function reject(User $u, Cotizacion $c): bool
    {
        return $this->approve($u, $c);
    }
}
