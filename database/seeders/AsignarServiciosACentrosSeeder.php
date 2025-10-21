<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServicioEmpresa;
use App\Models\CentroTrabajo;
use App\Models\ServicioCentro;

class AsignarServiciosACentrosSeeder extends Seeder {
  public function run(): void {
    $servicios = ServicioEmpresa::all();
    $centros = CentroTrabajo::all();

    foreach ($servicios as $s) {
      foreach ($centros as $c) {
        ServicioCentro::firstOrCreate([
          'id_servicio' => $s->id,
          'id_centrotrabajo' => $c->id,
        ],[
          'precio_base' => 0.00,
        ]);
      }
    }
  }
}
