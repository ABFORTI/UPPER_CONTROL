<?php

// app/Listeners/NotifyBackupFailed.php
namespace App\Listeners;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use App\Models\User;
use App\Notifications\SystemEventNotification;

class NotifyBackupFailed
{
    public function handle($event): void
    {
        // Notifica a todos los admins
        User::role('admin')->get()->each(fn($u) =>
            $u->notify(new SystemEventNotification(
                'Backup FALLÓ',
                'Revisa el log de respaldos. '.class_basename($event),
                route('admin.backups.index')
            ))
        );
    }
}
