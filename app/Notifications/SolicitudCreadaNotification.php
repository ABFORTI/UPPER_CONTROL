<?php

// app/Notifications/SolicitudCreadaNotification.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Solicitud;

class SolicitudCreadaNotification extends Notification {
  use Queueable;
  public function __construct(public Solicitud $solicitud) {}
  public function via($notifiable){ return ['mail']; }
  public function toMail($notifiable){
    return (new \Illuminate\Notifications\Messages\MailMessage)
      ->subject('Nueva Solicitud: '.$this->solicitud->folio)
      ->line('Se ha creado una nueva solicitud.')
      ->action('Ver', url(route('solicitudes.show',$this->solicitud->id)));
  }
}
