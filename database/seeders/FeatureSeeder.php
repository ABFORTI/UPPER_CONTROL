<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    /**
     * Seed inicial del catálogo de features.
     *
     * Puedes agregar nuevas funcionalidades añadiendo nuevas filas aquí (o desde BD).
     * La lógica del sistema se basa en `key`, no en IDs.
     */
    public function run(): void
    {
        Feature::updateOrCreate(
            ['key' => 'ver_cotizacion'],
            [
                'nombre' => 'Ver cotizaciones',
                'descripcion' => 'Permite acceder a las pantallas y rutas de Cotizaciones.',
            ]
        );

        Feature::updateOrCreate(
            ['key' => 'subir_excel'],
            [
                'nombre' => 'Subir solicitudes por Excel',
                'descripcion' => 'Permite subir/parsear Excel para precargar solicitudes y descargar Excel origen.',
            ]
        );
    }
}
