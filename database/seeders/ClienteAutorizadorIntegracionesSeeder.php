<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega el rol Cliente_Autorizador_Integraciones y el permiso
 * autorizar_integraciones_python al sistema.
 *
 * Ejecutar con:
 *   php artisan db:seed --class=ClienteAutorizadorIntegracionesSeeder
 */
class ClienteAutorizadorIntegracionesSeeder extends Seeder
{
    public function run(): void
    {
        // Permiso granular (útil si en el futuro se quiere asignar solo el permiso sin el rol)
        $permission = Permission::firstOrCreate([
            'name'       => 'autorizar_integraciones_python',
            'guard_name' => 'web',
        ]);

        // Rol principal
        $role = Role::firstOrCreate([
            'name'       => 'Cliente_Autorizador_Integraciones',
            'guard_name' => 'web',
        ]);

        // Asignar permiso al rol
        if (!$role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        $this->command->info('Rol Cliente_Autorizador_Integraciones y permiso autorizar_integraciones_python creados/verificados.');
    }
}
