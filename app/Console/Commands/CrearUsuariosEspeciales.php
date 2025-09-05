<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;

class CrearUsuariosEspeciales extends Command
{
    protected $signature = 'crear:usuarios-especiales';
    protected $description = 'Crea usuarios con roles Calidad y Facturacion';

    public function handle()
    {
        $roles = ['calidad', 'facturacion'];
        foreach ($roles as $rol) {
            Role::firstOrCreate(['name' => $rol]);
        }

        $usuarios = [
            [
                'name' => 'Calidad',
                'email' => 'calidad@gmail.com',
                'password' => bcrypt('123'),
                'centro_trabajo_id' => 1,
                'rol' => 'calidad',
            ],
            [
                'name' => 'Facturacion',
                'email' => 'facturacion@gmail.com',
                'password' => bcrypt('123'),
                'centro_trabajo_id' => 1,
                'rol' => 'facturacion',
            ],
        ];

        foreach ($usuarios as $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'centro_trabajo_id' => $data['centro_trabajo_id'],
            ]);
            $user->assignRole($data['rol']);
            $this->info("Usuario {$data['name']} creado y rol {$data['rol']} asignado: {$user->email}");
        }
    }
}
