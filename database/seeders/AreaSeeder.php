<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Area;
use App\Models\CentroTrabajo;

class AreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener todos los centros de trabajo
        $centros = CentroTrabajo::all();

        if ($centros->isEmpty()) {
            $this->command->warn('No hay centros de trabajo. Crea centros antes de ejecutar este seeder.');
            return;
        }

        // Áreas comunes para todos los centros
        $areasComunes = [
            ['nombre' => 'Almacén', 'descripcion' => 'Área de almacenamiento y bodega'],
            ['nombre' => 'Producción', 'descripcion' => 'Área de manufactura y producción'],
            ['nombre' => 'Calidad', 'descripcion' => 'Control y aseguramiento de calidad'],
            ['nombre' => 'Mantenimiento', 'descripcion' => 'Mantenimiento de equipos e instalaciones'],
            ['nombre' => 'Oficinas', 'descripcion' => 'Área administrativa'],
            ['nombre' => 'Empaque', 'descripcion' => 'Área de empaque y embalaje'],
            ['nombre' => 'Recepción', 'descripcion' => 'Recepción de materiales'],
            ['nombre' => 'Embarques', 'descripcion' => 'Embarques y logística'],
        ];

        foreach ($centros as $centro) {
            foreach ($areasComunes as $areaData) {
                Area::create([
                    'id_centrotrabajo' => $centro->id,
                    'nombre' => $areaData['nombre'],
                    'descripcion' => $areaData['descripcion'],
                    'activo' => true,
                ]);
            }
            $this->command->info("Áreas creadas para: {$centro->nombre}");
        }

        $this->command->info('✅ Seeder de áreas completado.');
    }
}
