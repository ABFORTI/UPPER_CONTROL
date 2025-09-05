<?php

// database/seeders/RolesSeeder.php
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

