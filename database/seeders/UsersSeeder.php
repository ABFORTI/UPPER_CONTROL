<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\CentroTrabajo;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
    // Seleccionar centros por defecto de la nueva lista
    $centro       = CentroTrabajo::where('prefijo','INGCEDIM')->first() ?? CentroTrabajo::first();
    $centroAlt    = CentroTrabajo::where('prefijo','CVAGDL')->first() ?? CentroTrabajo::skip(1)->first() ?? $centro;

        // Definir usuarios por rol
        $usersByRole = [
            'admin'         => ['name' => 'Admin',        'email' => 'admin@gmail.com'],
            'facturacion'   => ['name' => 'Facturacion',  'email' => 'facturacion@gmail.com'],
            'calidad'       => ['name' => 'Calidad',      'email' => 'calidad@gmail.com'],
            'team_leader'   => ['name' => 'Team Leader',  'email' => 'teamleader@gmail.com'],
            'coordinador'   => ['name' => 'Coordinador',  'email' => 'coordinador@gmail.com'],
            'Cliente_Supervisor'    => ['name' => 'Supervisor',   'email' => 'supervisor@gmail.com'],
        ];

        foreach ($usersByRole as $role => $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => bcrypt('password'), // contraseña por defecto
                    'centro_trabajo_id' => optional($centro)->id,
                    'activo' => true,
                ]
            );

            // Asignar rol si aún no lo tiene
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            // Asegurar centro principal (legacy)
            if ($centro && $user->centro_trabajo_id !== $centro->id) {
                $user->update(['centro_trabajo_id' => $centro->id]);
            }

            // Si es admin, facturacion, calidad, control o comercial: asignar múltiples centros en la pivote
            if ($centro && $centroAlt && in_array($role, ['admin','facturacion','calidad','control','comercial'], true)) {
                $ids = array_values(array_unique([optional($centro)->id, optional($centroAlt)->id]));
                $ids = array_filter($ids);
                if (!empty($ids)) {
                    $user->centros()->syncWithoutDetaching($ids);
                }
            }
        }
    }
}
