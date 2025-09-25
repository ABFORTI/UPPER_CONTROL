<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder {
  public function run(): void {
    foreach (['cliente','coordinador','team_leader','calidad','facturacion','admin'] as $r) {
      Role::firstOrCreate(['name'=>$r,'guard_name'=>'web']);
    }
  }
}

class DatabaseSeeder extends Seeder {
  public function run(): void {
    // Ejecuta seeders base
    $this->call([
      RolesSeeder::class,
      ServiciosSeeder::class,
      UsersSeeder::class,
      ServicioTamanosJumboSeeder::class, // asegura precio 'jumbo' por centro/servicio
    ]);
  }
}

