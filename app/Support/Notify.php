<?php

namespace App\Support;

use App\Models\User;

class Notify
{
    /** Usuarios por rol y centro */
    public static function usersByRoleAndCenter(string $role, int $centroId)
    {
        return User::role($role)->where('centro_trabajo_id', $centroId)->get();
    }

    /** Notificar a una colección de usuarios */
    public static function send($users, $notification): void
    {
        foreach ($users as $u) { $u->notify($notification); }
    }
}
