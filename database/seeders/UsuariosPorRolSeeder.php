<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\CentroTrabajo;
use Spatie\Permission\Models\Role;

class UsuariosPorRolSeeder extends Seeder
{
    public function run(): void
    {
        // Centros base
        $centro    = CentroTrabajo::orderBy('id')->first();
        $centroAlt = CentroTrabajo::orderBy('id')->skip(1)->first();

    // Crear un único usuario por cada rol existente
    $roles = Role::query()->pluck('name')->toArray();

        foreach ($roles as $role) {
            // Email único por rol con dominio @gmail.com
            $local = str_replace(' ', '_', strtolower($role));
            $email = sprintf('%s@gmail.com', $local);

            // Eliminar usuarios adicionales que tengan este rol pero distinto email estándar
            $emailStd = $email;
            $idsRole = User::role($role)->where('email', '!=', $emailStd)->pluck('id')->all();
            if (!empty($idsRole)) {
                // Borrar en bloque los extras (mantenerá FK por cascada/null según migraciones)
                User::whereIn('id', $idsRole)->delete();
            }

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => ucfirst($role),
                    'password' => bcrypt('password'),
                    'centro_trabajo_id' => optional($centro)->id,
                    'activo' => true,
                ]
            );

            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            // Asignaciones de centros en pivote según rol
            $multi = ['admin','facturacion','calidad','control','comercial','gerente_upper'];
            if (in_array($role, $multi, true) && $centro && $centroAlt) {
                $ids = array_filter([optional($centro)->id, optional($centroAlt)->id]);
                if (!empty($ids)) {
                    $user->centros()->syncWithoutDetaching($ids);
                }
            }

            // Para 'gerente' (antes 'cliente_centro') asegurar al menos 1 centro en pivote
            if ($role === 'Cliente_Gerente' && $centro) {
                $user->centros()->syncWithoutDetaching([ $centro->id ]);
            }
            // Log informativo por rol procesado
            if (method_exists($this->command, 'info')) {
                $this->command->info("Usuario único para rol '{$role}': {$user->email}");
            }
        }

        if (method_exists($this->command, 'info')) {
            $this->command->info('✅ Usuarios por rol listos (uno por rol).');
        }
    }
}
