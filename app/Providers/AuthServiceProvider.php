<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\Solicitud;
use App\Models\Orden;
use App\Models\Factura;
use App\Models\Cotizacion;
use App\Policies\SolicitudPolicy;
use App\Policies\OrdenPolicy;
use App\Policies\FacturaPolicy;
use App\Policies\CotizacionPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Solicitud::class => SolicitudPolicy::class,
        Orden::class     => OrdenPolicy::class,
        Factura::class   => FacturaPolicy::class,
        Cotizacion::class => CotizacionPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
