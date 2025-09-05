<?php

// database/seeders/ServiciosSeeder.php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\ServicioEmpresa;

class ServiciosSeeder extends Seeder {
  public function run(): void {
    $map = [
      'Distribución' => true,
      'Surtido'      => true,
      'Embalaje'     => true,
      'Almacenaje'   => false,
      'Transporte'   => false,
    ];
    foreach ($map as $nombre=>$usa) {
      ServicioEmpresa::firstOrCreate(['nombre'=>$nombre], ['usa_tamanos'=>$usa]);
    }
  }
}

