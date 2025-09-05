<?php

// app/Notifications/CalidadResultadoNotification.php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Orden;

class CalidadResultadoNotification extends Notification {
  use Queueable;
  public function __construct(public Orden $orden, public string $resultado, public ?string $obs=null) {}
  public function via($notifiable){ return ['mail']; }
  public function toMail($notifiable){
    $msg = (new \Illuminate\Notifications\Messages\MailMessage)
      ->subject('Calidad: '.$this->resultado.' — OT #'.$this->orden->id)
      ->line('Resultado de calidad: '.$this->resultado)
      ->action('Ver OT', url(route('ordenes.show',$this->orden->id)));
    if ($this->obs) $msg->line('Observaciones: '.$this->obs);
    return $msg;
  }
}
