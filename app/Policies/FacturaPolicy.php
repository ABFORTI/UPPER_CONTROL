<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Factura;

class FacturaPolicy
{
    public function view(User $u, Factura $f): bool {
        if ($u->hasRole('admin')) return true;
        // Facturación puede ver todas las facturas
        if ($u->hasRole('facturacion')) return true;
        // cliente puede ver su factura
        if ($u->hasRole('cliente')) return $f->orden->solicitud?->id_cliente === $u->id;
        return false;
    }

    public function operar(User $u, Factura $f): bool {
        // Admin y facturación pueden operar cualquier factura
        return $u->hasRole('admin') || $u->hasRole('facturacion');
    }
}
