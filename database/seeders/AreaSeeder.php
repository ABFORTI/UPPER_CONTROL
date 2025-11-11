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
        // Obtener todos los centros definitivos (tras limpiar seeder de centros)
        $centros = CentroTrabajo::where('activo', true)->get();

        if ($centros->isEmpty()) {
            $this->command->warn('No hay centros de trabajo. Crea centros antes de ejecutar este seeder.');
            return;
        }

        // NUEVAS LISTAS POR CENTRO (reemplazo total)
        // Clave por prefijo definido en CentrosSeeder
        $areasPorCentro = [
            'INGCEDIC' => [
                'EMPAQUES GENERALES',
                'MOVILIDAD',
                'RETAIL',
                'RECIBO',
                'RECIBO NACIONAL',
                'PROYECTOS',
                'INSUMOS',
            ],
            'INGCEDIM' => [
                'EMPAQUES GENERALES',
                'MOVILIDAD',
                'RETAIL',
                'RECIBO',
                'RECIBO NACIONAL',
                'PROYECTOS',
                'INSUMOS',
            ],
            'CVA' => [
                'RECIBO',
                'SURTIDO',
                'DESPACHO',
                'CONFIGURACIONES',
                'CLIENTES FMM',
            ],
            'CVAGDL' => [
                'RECIBO',
                'SURTIDO',
                'DESPACHO',
                'CONFIGURACIONES',
                'CLIENTES FMM',
            ],
            'CEVA' => [
                'PROYECTOS',
                'DAIKIN',
            ],
        ];

        foreach ($centros as $centro) {
            // Lista deseada EXACTA para este centro (si no definida, lista vacía)
            $deseadas = collect($areasPorCentro[$centro->prefijo] ?? [])
                ->filter(fn ($n) => filled($n))
                ->unique()
                ->values();

            // Crear o actualizar cada área deseada de manera idempotente
            foreach ($deseadas as $nombreArea) {
                Area::updateOrCreate(
                    [
                        'id_centrotrabajo' => $centro->id,
                        'nombre' => $nombreArea,
                    ],
                    [
                        'descripcion' => null,
                        'activo' => true,
                    ]
                );
            }

            // Desactivar áreas que ya no estén en la lista deseada
            $nombresDeseados = $deseadas->all();
            Area::where('id_centrotrabajo', $centro->id)
                ->whereNotIn('nombre', $nombresDeseados)
                ->update(['activo' => false]);

            $this->command->info("Áreas sincronizadas para: {$centro->nombre} ({$centro->prefijo})");
        }

        $this->command->info('✅ Seeder de áreas completado (idempotente y por centro).');
    }
}
