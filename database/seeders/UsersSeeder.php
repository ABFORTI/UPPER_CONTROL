<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('admin');

        // Asignar centro_trabajo_id usando el prefijo 'UMX'
        $centro = \App\Models\CentroTrabajo::where('prefijo', 'UMX')->first();
        if ($centro) {
            $user->update(['centro_trabajo_id' => $centro->id]);
        }
    }
}
