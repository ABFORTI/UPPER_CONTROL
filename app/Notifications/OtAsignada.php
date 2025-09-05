<?php

namespace App\Notifications;

use App\Models\Orden;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OtAsignada extends Notification
{
    use Queueable;

    public function __construct(public Orden $orden) {}

    public function via($notifiable): array { return ['database','mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nueva OT asignada')
            ->line("Se te asignó la OT #{$this->orden->id} ({$this->orden->servicio?->nombre}).")
            ->action('Ver OT', route('ordenes.show', $this->orden));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'ot_asignada',
            'orden_id' => $this->orden->id,
            'mensaje' => "OT #{$this->orden->id} asignada",
            'url' => route('ordenes.show', $this->orden),
        ];
    }
}
