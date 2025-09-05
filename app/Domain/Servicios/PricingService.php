<?php
namespace App\Domain\Servicios;

use App\Models\ServicioCentro;
use App\Models\ServicioTamano;

class PricingService {
  public function precioUnitario(int $centroId, int $servicioId, ?string $tamano=null): float {
    $sc = ServicioCentro::where('id_centrotrabajo',$centroId)
          ->where('id_servicio',$servicioId)->firstOrFail();

    if (optional($sc->servicio)->usa_tamanos) {
      if (!$tamano) throw new \InvalidArgumentException('Tamaño requerido');
      $st = ServicioTamano::where('id_servicio_centro',$sc->id)
            ->where('tamano',$tamano)->firstOrFail();
      return (float)$st->precio;
    }
    return (float)$sc->precio_base;
  }
}
