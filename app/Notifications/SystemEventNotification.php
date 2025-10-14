<?php

// app/Notifications/SystemEventNotification.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SystemEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null
    ) {}

    public function via($notifiable){ 
        return ['database', 'mail']; 
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting("Hola {$notifiable->name},")
            ->line($this->message);
        
        if ($this->url) {
            $mail->action('Ver Detalles', $this->url);
        }
        
        return $mail->line('Gracias por usar Upper Control.');
    }

    public function toDatabase($notifiable){
        return [
            'title'   => $this->title,
            'message' => $this->message,
            'url'     => $this->url,
        ];
    }
}


