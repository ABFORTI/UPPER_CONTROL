<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ControlComercialRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Definir permisos CRUD para servicios y areas
        $resources = ['servicios', 'areas'];
        $actions = ['view', 'list', 'create', 'edit', 'delete'];

        $perms = [];
        foreach ($resources as $res) {
            foreach ($actions as $a) {
                $name = "{$res}.{$a}";
                $perms[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }
        }

        // Crear roles y asignar todos los permisos de servicios y areas
        foreach (['control', 'comercial'] as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }
    }
}
