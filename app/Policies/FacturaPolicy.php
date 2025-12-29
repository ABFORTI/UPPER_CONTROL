<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Factura;

class FacturaPolicy
{
    public function view(User $u, Factura $f): bool {
        if ($u->hasRole('admin')) return true;
        // Gerente Upper: puede ver todas las facturas
        if ($u->hasRole('gerente_upper')) return true;
        // Facturación puede ver todas las facturas
        if ($u->hasRole('facturacion')) return true;
        // Gerente (antes 'cliente_centro'): puede ver facturas de sus centros
        if ($u->hasRole('Cliente_Gerente')) {
            $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v)=>(int)$v)->all();
            $primary = (int)($u->centro_trabajo_id ?? 0);
            if ($primary) $ids[] = $primary;
            $ids = array_values(array_unique($ids));
            return in_array((int)($f->orden?->id_centrotrabajo ?? 0), $ids, true);
        }
        // supervisor (antes 'cliente') puede ver su factura
        if ($u->hasRole('Cliente_Supervisor')) return $f->orden->solicitud?->id_cliente === $u->id;
        return false;
    }

    public function operar(User $u, Factura $f): bool {
        // Admin y facturación pueden operar cualquier factura
        return $u->hasRole('admin') || $u->hasRole('facturacion');
    }
}
