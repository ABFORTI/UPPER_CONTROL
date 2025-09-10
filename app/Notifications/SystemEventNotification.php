<?php

// app/Notifications/SystemEventNotification.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SystemEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null
    ) {}

    public function via($notifiable){ return ['database']; }

    public function toDatabase($notifiable){
        return [
            'title'   => $this->title,
            'message' => $this->message,
            'url'     => $this->url,
        ];
    }
}

