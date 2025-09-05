<?php

namespace App\Notifications;

use App\Models\Orden;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OtValidadaParaCliente extends Notification
{
    use Queueable;

    public function __construct(public Orden $orden) {}

    public function via($notifiable): array { return ['database','mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Validación de calidad completada')
            ->line("La OT #{$this->orden->id} fue validada por calidad.")
            ->action('Autorizar OT', route('ordenes.show', $this->orden));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'ot_validada_cliente',
            'orden_id' => $this->orden->id,
            'mensaje' => "OT #{$this->orden->id} validada; pendiente autorización del cliente.",
            'url' => route('ordenes.show', $this->orden),
        ];
    }
}
