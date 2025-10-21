<?php

// app/Notifications/ClienteAutorizoNotification.php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Orden;

class ClienteAutorizoNotification extends Notification {
  use Queueable;
  
  public function __construct(public Orden $orden) {}
  
  public function via($notifiable){ return ['mail']; }
  
  public function toMail($notifiable){
    return (new MailMessage)
      ->subject('✅ Cliente Autorizó la OT #' . $this->orden->id)
      ->greeting('¡Hola ' . $notifiable->name . '!')
      ->line('Te informamos que el **cliente ha autorizado** la Orden de Trabajo #' . $this->orden->id . '.')
      ->line('')
      ->line('**Información de la OT:**')
      ->line('• **Número:** #' . $this->orden->id)
      ->line('• **Servicio:** ' . ($this->orden->servicio?->nombre ?? 'N/A'))
      ->line('• **Centro:** ' . ($this->orden->centro?->nombre ?? 'N/A'))
      ->line('• **Estado:** ✅ Autorizada por Cliente')
      ->line('')
      ->line('Esta orden ya puede **proceder a facturación**.')
      ->action('📋 Ver Orden de Trabajo', route('ordenes.show', $this->orden->id))
      ->line('')
      ->line('Notificación automática del sistema.')
      ->salutation('Saludos,  
**Sistema Upper Control**');
  }
}


