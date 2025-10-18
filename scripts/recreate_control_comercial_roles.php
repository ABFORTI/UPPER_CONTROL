<?php
// scripts/recreate_control_comercial_roles.php
// Recrear roles control y comercial desde cero

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== RECREANDO ROLES CONTROL Y COMERCIAL ===\n\n";

// 1. Eliminar roles existentes y sus asignaciones
echo "1. Eliminando roles existentes...\n";
$rolesToDelete = ['control', 'comercial'];
foreach ($rolesToDelete as $roleName) {
    $role = Role::where('name', $roleName)->first();
    if ($role) {
        // Desasignar de usuarios
        DB::table('model_has_roles')->where('role_id', $role->id)->delete();
        // Eliminar permisos del rol
        $role->permissions()->detach();
        // Eliminar rol
        $role->delete();
        echo "   ✅ Rol '$roleName' eliminado\n";
    }
}

// 2. Limpiar cache de permisos
echo "\n2. Limpiando caché de permisos...\n";
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
echo "   ✅ Caché limpiada\n";

// 3. Crear permisos si no existen
echo "\n3. Verificando/creando permisos...\n";
$resources = ['servicios', 'areas'];
$actions = ['view', 'list', 'create', 'edit', 'delete'];

foreach ($resources as $resource) {
    foreach ($actions as $action) {
        $permName = "{$resource}.{$action}";
        $perm = Permission::firstOrCreate(
            ['name' => $permName, 'guard_name' => 'web']
        );
        echo "   ✅ Permiso: $permName\n";
    }
}

// 4. Crear roles control y comercial
echo "\n4. Creando roles...\n";
$roleControl = Role::create(['name' => 'control', 'guard_name' => 'web']);
$roleComercial = Role::create(['name' => 'comercial', 'guard_name' => 'web']);
echo "   ✅ Rol 'control' creado (ID: {$roleControl->id})\n";
echo "   ✅ Rol 'comercial' creado (ID: {$roleComercial->id})\n";

// 5. Asignar todos los permisos de servicios y areas a ambos roles
echo "\n5. Asignando permisos a roles...\n";
$allPerms = Permission::whereIn('name', [
    'servicios.view', 'servicios.list', 'servicios.create', 'servicios.edit', 'servicios.delete',
    'areas.view', 'areas.list', 'areas.create', 'areas.edit', 'areas.delete',
])->get();

$roleControl->syncPermissions($allPerms);
$roleComercial->syncPermissions($allPerms);
echo "   ✅ Permisos asignados a 'control'\n";
echo "   ✅ Permisos asignados a 'comercial'\n";

// 6. Crear/actualizar usuarios de prueba
echo "\n6. Creando usuarios de prueba...\n";

$centros = \App\Models\CentroTrabajo::take(2)->pluck('id')->toArray();
if (empty($centros)) {
    echo "   ⚠️  No hay centros de trabajo en la BD\n";
    $centros = [1]; // fallback
}

// Usuario Control
$userControl = User::updateOrCreate(
    ['email' => 'control@gmail.com'],
    [
        'name' => 'Control',
        'password' => bcrypt('password'),
        'centro_trabajo_id' => $centros[0],
        'activo' => true,
    ]
);
// Eliminar roles anteriores y asignar solo control
$userControl->syncRoles(['control']);
// Asignar centros múltiples
if (count($centros) > 0) {
    $userControl->centros()->sync($centros);
}
echo "   ✅ Usuario: control@gmail.com (password: password)\n";
echo "      - Rol: control\n";
echo "      - Centros: " . implode(', ', $centros) . "\n";

// Usuario Comercial
$userComercial = User::updateOrCreate(
    ['email' => 'comercial@gmail.com'],
    [
        'name' => 'Comercial',
        'password' => bcrypt('password'),
        'centro_trabajo_id' => $centros[0],
        'activo' => true,
    ]
);
$userComercial->syncRoles(['comercial']);
if (count($centros) > 0) {
    $userComercial->centros()->sync($centros);
}
echo "   ✅ Usuario: comercial@gmail.com (password: password)\n";
echo "      - Rol: comercial\n";
echo "      - Centros: " . implode(', ', $centros) . "\n";

// 7. Verificar creación
echo "\n7. Verificando configuración final...\n";
$testUser = User::where('email', 'control@gmail.com')->first();
echo "   Usuario control@gmail.com:\n";
echo "     - Roles: " . implode(', ', $testUser->roles->pluck('name')->toArray()) . "\n";
echo "     - Permisos: " . $testUser->getAllPermissions()->count() . " permisos\n";
echo "     - hasRole('control'): " . ($testUser->hasRole('control') ? 'TRUE' : 'FALSE') . "\n";
echo "     - can('servicios.list'): " . ($testUser->can('servicios.list') ? 'TRUE' : 'FALSE') . "\n";
echo "     - can('areas.list'): " . ($testUser->can('areas.list') ? 'TRUE' : 'FALSE') . "\n";

echo "\n✅ PROCESO COMPLETADO\n";
echo "\nCredenciales para probar:\n";
echo "  Email: control@gmail.com\n";
echo "  Password: password\n\n";
echo "  Email: comercial@gmail.com\n";
echo "  Password: password\n\n";
