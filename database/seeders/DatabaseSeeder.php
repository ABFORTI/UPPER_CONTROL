<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {
  public function run(): void {
    // Ejecuta seeders base
    $this->call([
      CentrosSeeder::class,
      RolesSeeder::class,
      ServiciosSeeder::class,
      UsersSeeder::class,
      ServicioTamanosJumboSeeder::class, // asegura precio 'jumbo' por centro/servicio
    ]);
  }
}

