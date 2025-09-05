<?php

namespace App\Notifications;

use App\Models\Orden;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OtListaParaCalidad extends Notification
{
    use Queueable;

    public function __construct(public Orden $orden) {}

    public function via($notifiable): array { return ['database','mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('OT lista para revisión de calidad')
            ->line("La OT #{$this->orden->id} quedó completada y está lista para revisar.")
            ->action('Revisar en calidad', route('calidad.show', $this->orden));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'ot_lista_calidad',
            'orden_id' => $this->orden->id,
            'mensaje' => "OT #{$this->orden->id} lista para calidad",
            'url' => route('calidad.show', $this->orden),
        ];
    }
}
