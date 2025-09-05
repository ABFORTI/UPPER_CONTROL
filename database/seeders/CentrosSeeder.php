<?php

// database/seeders/CentrosSeeder.php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\CentroTrabajo;

class CentrosSeeder extends Seeder {
  public function run(): void {
    CentroTrabajo::firstOrCreate(['prefijo'=>'UMX'], ['nombre'=>'Upper CDMX']);
    CentroTrabajo::firstOrCreate(['prefijo'=>'UGDL'], ['nombre'=>'Upper GDL']);
  }
}
