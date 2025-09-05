<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;

class CrearTeamLeader extends Command
{
    protected $signature = 'crear:teamleader {name} {email} {password} {centro_trabajo_id}';
    protected $description = 'Crea un usuario con rol team_leader';

    public function handle()
    {
        $role = Role::firstOrCreate(['name' => 'team_leader']);

        $user = User::create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => bcrypt($this->argument('password')),
            'centro_trabajo_id' => $this->argument('centro_trabajo_id'),
        ]);

        $user->assignRole($role);

        $this->info('Usuario creado y rol team_leader asignado: ' . $user->email);
    }
}
