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
            ->subject('🎯 Nueva OT Asignada #' . $this->orden->id)
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line('Se te ha asignado una nueva **Orden de Trabajo** para que la gestiones.')
            ->line('')
            ->line('**Información de la OT:**')
            ->line('• **Número:** #' . $this->orden->id)
            ->line('• **Servicio:** ' . ($this->orden->servicio?->nombre ?? 'N/A'))
            ->line('• **Centro de Trabajo:** ' . ($this->orden->centro?->nombre ?? 'N/A'))
            ->line('• **Fecha de Creación:** ' . $this->orden->created_at->format('d/m/Y H:i'))
            ->line('')
            ->line('Como **responsable de equipo**, es tu tarea coordinar y supervisar el trabajo.')
            ->action('👁️ Ver Orden de Trabajo', route('ordenes.show', $this->orden))
            ->line('')
            ->line('Recuerda registrar los avances y evidencias conforme se realice el trabajo.')
            ->salutation('Éxito en tu gestión,  
**Equipo Upper Control**');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Nueva OT Asignada',
            'message' => "Se te asignó la OT #{$this->orden->id} ({$this->orden->servicio?->nombre}).",
            'url' => route('ordenes.show', $this->orden),
            'type' => 'ot_asignada',
            'orden_id' => $this->orden->id,
        ];
    }
}
