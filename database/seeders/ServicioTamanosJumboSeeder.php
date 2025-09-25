<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServicioTamanosJumboSeeder extends Seeder
{
    public function run(): void
    {
        // Recorre todos los servicios_centro y asegura un registro tamano='jumbo'
        $centros = DB::table('servicios_centro')->select('id','precio_base')->get();
        foreach ($centros as $sc) {
            // Si ya existe 'jumbo', saltar
            $exists = DB::table('servicio_tamanos')
                ->where('id_servicio_centro', $sc->id)
                ->where('tamano', 'jumbo')
                ->exists();
            if ($exists) continue;

            // Intentar copiar precio de 'grande'
            $precioGrande = DB::table('servicio_tamanos')
                ->where('id_servicio_centro', $sc->id)
                ->where('tamano', 'grande')
                ->value('precio');

            $precio = $precioGrande ?? $sc->precio_base ?? 0;

            DB::table('servicio_tamanos')->insert([
                'id_servicio_centro' => $sc->id,
                'tamano'             => 'jumbo',
                'precio'             => $precio,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }
    }
}
