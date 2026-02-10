<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {
  public function run(): void {
    // Ejecuta seeders base
    $this->call([
      CentrosSeeder::class,
      FeatureSeeder::class,
      AreaSeeder::class,
      MarcasSeeder::class,
      RolesSeeder::class,
      ServiciosSeeder::class,
  AsignarServiciosACentrosSeeder::class,
      ServicioTamanosJumboSeeder::class, // asegura precio 'jumbo' por centro/servicio
      UsuariosPorRolSeeder::class,       // crea usuario demo por cada rol existente
    ]);
  }
}

