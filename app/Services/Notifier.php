<?php
// app/Services/Notifier.php
namespace App\Services;

use App\Models\User;
use App\Notifications\SystemEventNotification;

class Notifier
{
    /** 
     * Enviar a todos los usuarios con un rol en un centro
     * Considera tanto el centro principal como los centros adicionales asignados
     */
    public static function toRoleInCentro(string $role, int $centroId, string $title, string $msg, ?string $url=null): void
    {
        User::role($role)
            ->where(function($query) use ($centroId) {
                $query->where('centro_trabajo_id', $centroId)
                      ->orWhereHas('centros', function($q) use ($centroId) {
                          $q->where('centro_trabajo_id', $centroId);
                      });
            })
            ->get()
            ->each(fn($u)=> $u->notify(new SystemEventNotification($title,$msg,$url)));
    }

    /** Enviar directo a un usuario */
    public static function toUser(int $userId, string $title, string $msg, ?string $url=null): void
    {
        if ($u = User::find($userId)) $u->notify(new SystemEventNotification($title,$msg,$url));
    }
}
