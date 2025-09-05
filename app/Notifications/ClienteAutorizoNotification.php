<?php

// app/Notifications/ClienteAutorizoNotification.php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Orden;

class ClienteAutorizoNotification extends Notification {
  use Queueable;
  public function __construct(public Orden $orden) {}
  public function via($notifiable){ return ['mail']; }
  public function toMail($notifiable){
    return (new \Illuminate\Notifications\Messages\MailMessage)
      ->subject('Cliente autorizó OT #'.$this->orden->id)
      ->line('La OT fue autorizada por el cliente.')
      ->action('Abrir OT', url(route('ordenes.show',$this->orden->id)));
  }
}

