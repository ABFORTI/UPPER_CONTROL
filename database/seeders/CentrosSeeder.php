<?php

// database/seeders/CentrosSeeder.php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\CentroTrabajo;

class CentrosSeeder extends Seeder {
  public function run(): void {
    // Lista definitiva de centros
    $definitivos = [
      ['prefijo' => 'INGCEDIM', 'nombre' => 'INGRAM CEDIM'],
      ['prefijo' => 'INGCEDIC', 'nombre' => 'INGRAM CEDIC'],
      ['prefijo' => 'CVA',      'nombre' => 'CVA'],
      ['prefijo' => 'CVAGDL',   'nombre' => 'CVA GDL'],
      ['prefijo' => 'CEVA',     'nombre' => 'CEVA'],
    ];

    // Crear/actualizar los definitivos
    foreach ($definitivos as $c) {
      CentroTrabajo::updateOrCreate(
        ['prefijo' => $c['prefijo']],
        ['nombre' => $c['nombre'], 'activo' => true]
      );
    }

    // Desactivar (no eliminar) cualquier centro que no esté en la lista definitiva
    // para evitar conflictos con llaves foráneas en datos existentes.
    $prefijosPermitidos = array_column($definitivos, 'prefijo');
    CentroTrabajo::whereNotIn('prefijo', $prefijosPermitidos)
      ->update(['activo' => false]);
  }
}
