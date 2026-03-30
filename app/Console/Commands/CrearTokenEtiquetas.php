<?php

namespace App\Console\Commands;

use App\Models\CentroTrabajo;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class CrearTokenEtiquetas extends Command
{
    protected $signature = 'integracion:crear-token-etiquetas
        {--email=etiquetas@gmail.com : Email del usuario tecnico}
        {--name=Integracion Etiquetas : Nombre del usuario tecnico}
        {--password= : Password opcional para el usuario tecnico}
        {--centro=CVA GDL : Centro de trabajo principal del usuario tecnico}
        {--token-name=python-etiquetas : Nombre identificable del token}
        {--regenerar-password : Fuerza la actualizacion del password si el usuario ya existe}';

    protected $description = 'Crea o reutiliza el usuario tecnico de etiquetas y genera un token Sanctum listo para copiar';

    public function handle(): int
    {
        if (!$this->sanctumReady()) {
            return self::FAILURE;
        }

        if (!Schema::hasTable('roles')) {
            $this->error('No existe la tabla roles. Ejecuta las migraciones de permisos antes de crear el usuario tecnico.');
            return self::FAILURE;
        }

        $email = trim((string) $this->option('email'));
        $name = trim((string) $this->option('name'));
        $tokenName = trim((string) $this->option('token-name'));
        $centroName = trim((string) $this->option('centro'));
        $plainPassword = (string) ($this->option('password') ?? '');
        $resetPassword = (bool) $this->option('regenerar-password');

        if ($email === '' || $name === '' || $tokenName === '' || $centroName === '') {
            $this->error('Email, nombre, token-name y centro son obligatorios.');
            return self::FAILURE;
        }

        $centro = CentroTrabajo::query()
            ->where('nombre', $centroName)
            ->first();

        if (!$centro) {
            $this->error("No existe el centro de trabajo '{$centroName}'.");
            return self::FAILURE;
        }

        $role = Role::findOrCreate('integracion_api');
        $generatedPassword = false;

        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            if ($plainPassword === '') {
                $plainPassword = Str::random(32);
                $generatedPassword = true;
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($plainPassword),
                'centro_trabajo_id' => (int) $centro->id,
                'activo' => true,
            ]);

            $this->info('Usuario tecnico creado correctamente.');
        } else {
            $updates = [];

            if ($plainPassword !== '' && !$resetPassword) {
                $this->warn('Se proporciono --password pero no se aplicara porque falta --regenerar-password.');
            }

            if ($user->name !== $name) {
                $updates['name'] = $name;
            }
            if ((int) $user->centro_trabajo_id !== (int) $centro->id) {
                $updates['centro_trabajo_id'] = (int) $centro->id;
            }
            if (!$user->activo) {
                $updates['activo'] = true;
            }
            if ($resetPassword) {
                if ($plainPassword === '') {
                    $plainPassword = Str::random(32);
                    $generatedPassword = true;
                }

                $updates['password'] = Hash::make($plainPassword);
            }

            if (!empty($updates)) {
                $user->update($updates);
            }

            $this->info('Usuario tecnico existente reutilizado.');
        }

        if (!$user->hasRole($role->name)) {
            $user->assignRole($role);
        }

        $user->centros()->syncWithoutDetaching([(int) $centro->id]);

        $user->tokens()->where('name', $tokenName)->delete();
        $token = $user->createToken($tokenName)->plainTextToken;

        $this->newLine();
        $this->info('Integracion lista.');
        $this->line('Usuario tecnico: ' . $user->email);
        $this->line('Rol asignado: ' . $role->name);
        $this->line('Centro principal: ' . $centro->nombre);
        $this->line('Token name: ' . $tokenName);

        if ($resetPassword || $generatedPassword) {
            $this->warn('Password del usuario tecnico: ' . $plainPassword);
        }

        $this->newLine();
        $this->info('Token plano listo para copiar:');
        $this->line($token);
        $this->newLine();
        $this->info('Linea sugerida para .env del sistema Python:');
        $this->line('UPPER_CONTROL_TOKEN=' . $token);

        return self::SUCCESS;
    }

    private function sanctumReady(): bool
    {
        if (!class_exists(\Laravel\Sanctum\Sanctum::class)) {
            $this->error('Laravel Sanctum no esta instalado en vendor. Ejecuta composer install o agrega laravel/sanctum.');
            return false;
        }

        if (!in_array('Laravel\\Sanctum\\HasApiTokens', class_uses_recursive(User::class), true)) {
            $this->error('El modelo User no usa HasApiTokens. Agrega el trait en app/Models/User.php.');
            return false;
        }

        if (!Schema::hasTable('personal_access_tokens')) {
            $this->error('No existe la tabla personal_access_tokens.');
            $this->line('Ejecuta: php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider"');
            $this->line('Luego ejecuta: php artisan migrate');
            return false;
        }

        return true;
    }
}