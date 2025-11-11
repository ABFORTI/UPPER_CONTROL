<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Marca;
use App\Models\CentroTrabajo;

class MarcasSeeder extends Seeder
{
    public function run(): void
    {
        $centros = CentroTrabajo::where('activo', true)->get();
        if ($centros->isEmpty()) {
            $this->command->warn('No hay centros de trabajo. Crea centros antes de ejecutar este seeder.');
            return;
        }

        // NUEVAS LISTAS EXACTAS POR CENTRO (reemplazo total)
        $marcasPorCentro = [
            'INGCEDIC' => [
                'RECIBO NACIONAL',
                'PROYECTOS',
                'JBL',
                'META',
                'MSI',
                'SEARS',
                'COPPEL',
                'LIVERPOOL',
                'WALT MART',
                'ESI',
                'BOSE',
                'GOPRO',
                'COSTCO',
                'MICROSOFT',
                'SORIANA',
            ],
            'INGCEDIM' => [
                'RECIBO NACIONAL',
                'PROYECTOS',
                'JBL',
                'META',
                'MSI',
                'SEARS',
                'COPPEL',
                'LIVERPOOL',
                'WALT MART',
                'ESI',
                'BOSE',
                'GOPRO',
                'COSTCO',
                'MICROSOFT',
                'SORIANA',
            ],
            'CVA' => [
                'CVA',
                'BENQ',
                'FMM',
                'STRATECH',
                'SANTA CLARA',
                'RED ALLIENCE',
            ],
            'CVAGDL' => [
                // (Vacío en marcas según listado del usuario)
            ],
            'CEVA' => [
                'JABIL',
                'HONEYWELL',
                'DAIKIN',
                'TCL',
            ],
        ];

        foreach ($centros as $centro) {
            $lista = collect($marcasPorCentro[$centro->prefijo] ?? [])
                ->filter(fn ($n) => filled($n))
                ->unique()
                ->values();

            // Crear/actualizar por centro
            foreach ($lista as $nombre) {
                Marca::updateOrCreate(
                    [
                        'id_centrotrabajo' => $centro->id,
                        'nombre' => $nombre,
                    ],
                    [
                        'activo' => true,
                    ]
                );
            }

            // Desactivar marcas que no estén en la lista deseada
            Marca::where('id_centrotrabajo', $centro->id)
                ->whereNotIn('nombre', $lista->all())
                ->update(['activo' => false]);

            $this->command->info("Marcas sincronizadas para: {$centro->nombre} ({$centro->prefijo})");
        }

        $this->command->info('✅ Seeder de marcas completado (idempotente y por centro).');
    }
}
