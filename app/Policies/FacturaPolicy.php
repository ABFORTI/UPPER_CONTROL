<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Factura;

class FacturaPolicy
{
    public function view(User $u, Factura $f): bool {
        if ($u->hasRole('admin')) return true;
        if ($u->hasRole('facturacion')) {
            return (int)$u->centro_trabajo_id === (int)$f->orden->id_centrotrabajo;
        }
        // cliente puede ver su factura
        if ($u->hasRole('cliente')) return $f->orden->solicitud?->id_cliente === $u->id;
        return false;
    }

    public function operar(User $u, Factura $f): bool {
        return $u->hasRole('admin') ||
               ($u->hasRole('facturacion') && (int)$u->centro_trabajo_id === (int)$f->orden->id_centrotrabajo);
    }
}
