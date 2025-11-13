<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class Notify
{
    /** 
     * Usuarios por rol y centro
     * Considera tanto el centro principal como los centros adicionales asignados
     */
    public static function usersByRoleAndCenter(string $role, int $centroId)
    {
        return User::role($role)
            ->where(function($query) use ($centroId) {
                $query->where('centro_trabajo_id', $centroId)
                      ->orWhereHas('centros', function($q) use ($centroId) {
                          $q->where('centro_trabajo_id', $centroId);
                      });
            })
            ->get();
    }

    /** Notificar a una colección de usuarios */
    public static function send($users, $notification): void
    {
        foreach ($users as $u) {
            try {
                $u->notify($notification);
            } catch (\Throwable $e) {
                Log::warning('Notify.send: fallo al notificar (ignorado)', [
                    'user_id' => $u->id ?? null,
                    'type'    => get_class($notification),
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }
}
