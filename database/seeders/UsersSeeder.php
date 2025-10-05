<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Asegurar centro por defecto 'UMX'
        $centro = \App\Models\CentroTrabajo::firstOrCreate(['prefijo' => 'UMX'], ['nombre' => 'Upper CDMX']);

        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('admin');
        $user->update(['centro_trabajo_id' => $centro->id]);
    }
}
