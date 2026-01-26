<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        \Spatie\Backup\Events\BackupHasFailed::class => [
            \App\Listeners\NotifyBackupFailed::class,
        ],
        \Spatie\Backup\Events\CleanupHasFailed::class => [
            \App\Listeners\NotifyBackupFailed::class,
        ],
        \Spatie\Backup\Events\BackupWasSuccessful::class => [
            \App\Listeners\NotifyBackupOk::class,
        ],
        \App\Events\QuotationApproved::class => [
            \App\Listeners\CreateSolicitudesFromQuotationApproval::class,
        ],
        // ...otros listeners...
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}