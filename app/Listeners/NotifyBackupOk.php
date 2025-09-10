<?php

// app/Listeners/NotifyBackupOk.php
namespace App\Listeners;
use Spatie\Backup\Events\BackupWasSuccessful;
use App\Models\User;
use App\Notifications\SystemEventNotification;

class NotifyBackupOk
{
    public function handle(BackupWasSuccessful $event): void
    {
        User::role('admin')->get()->each(fn($u) =>
            $u->notify(new SystemEventNotification(
                'Backup OK',
                'El respaldo se completó correctamente.',
                route('admin.backups.index')
            ))
        );
    }
}
