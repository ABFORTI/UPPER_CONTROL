<?php

// app/Notifications/SystemEventNotification.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SystemEventNotification extends Notification // Sin ShouldQueue para ejecución sincrónica
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
            ->subject('🔔 ' . $this->title)
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line($this->message)
            ->line('');
        
        if ($this->url) {
            $mail->action('🔗 Ver Detalles', $this->url)
                 ->line('');
        }
        
        return $mail->line('Este es un mensaje automático del sistema.')
                    ->salutation('Atentamente,  
**Sistema Upper Control**');
    }

    public function toDatabase($notifiable){
        return [
            'title'   => $this->title,
            'message' => $this->message,
            'url'     => $this->url,
        ];
    }
}


