<?php

// app/Notifications/SolicitudCreadaNotification.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Solicitud;

class SolicitudCreadaNotification extends Notification {
  use Queueable;
  
  public function __construct(public Solicitud $solicitud) {}
  
  public function via($notifiable){ return ['mail']; }
  
  public function toMail($notifiable){
    return (new MailMessage)
      ->subject('📝 Nueva Solicitud de Servicio - ' . $this->solicitud->folio)
      ->greeting('¡Hola ' . $notifiable->name . '!')
      ->line('Se ha recibido una nueva solicitud de servicio que requiere tu atención.')
      ->line('')
      ->line('**Detalles de la Solicitud:**')
      ->line('• **Folio:** ' . $this->solicitud->folio)
      ->line('• **Servicio:** ' . ($this->solicitud->servicio?->nombre ?? 'N/A'))
      ->line('• **Centro de Trabajo:** ' . ($this->solicitud->centro?->nombre ?? 'N/A'))
      ->line('• **Tamaño:** ' . ($this->solicitud->tamano ?? 'N/A'))
      ->line('• **Fecha:** ' . $this->solicitud->created_at->format('d/m/Y H:i'))
      ->line('')
      ->line('Por favor, revisa la solicitud y procede con su aprobación o rechazo.')
      ->action('📋 Revisar Solicitud', route('solicitudes.show', $this->solicitud->id))
      ->line('')
      ->line('Este es un mensaje automático del sistema Upper Control.')
      ->salutation('Saludos cordiales,  
**Equipo Upper Control**');
  }
}
