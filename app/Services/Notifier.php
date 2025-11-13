<?php
// app/Services/Notifier.php
namespace App\Services;

use App\Models\User;
use App\Notifications\SystemEventNotification;
use Illuminate\Support\Facades\Log;

class Notifier
{
    /** 
     * Enviar a todos los usuarios con un rol en un centro
     * Considera tanto el centro principal como los centros adicionales asignados
     */
    public static function toRoleInCentro(string $role, int $centroId, string $title, string $msg, ?string $url=null): void
    {
        $query = User::role($role)
            ->where(function($query) use ($centroId) {
                $query->where('centro_trabajo_id', $centroId)
                      ->orWhereHas('centros', function($q) use ($centroId) {
                          $q->where('centro_trabajo_id', $centroId);
                      });
            });

        $targets = $query->get();
        Log::info('Notifier.toRoleInCentro debug', [
            'role' => $role,
            'centro_id' => $centroId,
            'count' => $targets->count(),
            'ids' => $targets->pluck('id')->all(),
        ]);

        $targets->each(function($u) use ($title,$msg,$url) {
            try { $u->notify(new SystemEventNotification($title,$msg,$url)); }
            catch (\Throwable $e) {
                Log::warning('Notifier.toRoleInCentro: fallo al notificar (ignorado)', [
                    'user_id' => $u->id ?? null,
                    'error'   => $e->getMessage(),
                ]);
            }
        });
    }

    /** Enviar directo a un usuario */
    public static function toUser(int $userId, string $title, string $msg, ?string $url=null): void
    {
        if ($u = User::find($userId)) {
            try { $u->notify(new SystemEventNotification($title,$msg,$url)); }
            catch (\Throwable $e) {
                Log::warning('Notifier.toUser: fallo al notificar (ignorado)', [
                    'user_id' => $userId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }
}
