<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\CentroTrabajo;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Asegurar centro por defecto 'UMX'
        $centro = CentroTrabajo::firstOrCreate(['prefijo' => 'UMX'], ['nombre' => 'Upper CDMX']);
        $centroGdl = CentroTrabajo::firstOrCreate(['prefijo' => 'UGDL'], ['nombre' => 'Upper GDL']);

        // Definir usuarios por rol
        $usersByRole = [
            'admin'         => ['name' => 'Admin',        'email' => 'admin@gmail.com'],
            'facturacion'   => ['name' => 'Facturacion',  'email' => 'facturacion@gmail.com'],
            'calidad'       => ['name' => 'Calidad',      'email' => 'calidad@gmail.com'],
            'team_leader'   => ['name' => 'Team Leader',  'email' => 'teamleader@gmail.com'],
            'coordinador'   => ['name' => 'Coordinador',  'email' => 'coordinador@gmail.com'],
            'cliente'       => ['name' => 'Cliente',      'email' => 'cliente@gmail.com'],
        ];

        foreach ($usersByRole as $role => $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => bcrypt('password'), // contraseña por defecto
                    'centro_trabajo_id' => $centro->id,
                    'activo' => true,
                ]
            );

            // Asignar rol si aún no lo tiene
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            // Asegurar centro principal (legacy)
            if ($user->centro_trabajo_id !== $centro->id) {
                $user->update(['centro_trabajo_id' => $centro->id]);
            }

            // Si es admin, facturacion o calidad: asignar múltiples centros en la pivote
            if (in_array($role, ['admin','facturacion','calidad'], true)) {
                $ids = [$centro->id, $centroGdl->id];
                $user->centros()->syncWithoutDetaching($ids);
            }
        }
    }
}
