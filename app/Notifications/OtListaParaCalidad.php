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
            ->subject('✅ OT #' . $this->orden->id . ' Lista para Revisión de Calidad')
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line('Una **Orden de Trabajo** ha sido completada y está lista para tu revisión de calidad.')
            ->line('')
            ->line('**Detalles de la OT:**')
            ->line('• **Número:** #' . $this->orden->id)
            ->line('• **Servicio:** ' . ($this->orden->servicio?->nombre ?? 'N/A'))
            ->line('• **Centro de Trabajo:** ' . ($this->orden->centro?->nombre ?? 'N/A'))
            ->line('• **Responsable:** ' . ($this->orden->responsable?->name ?? 'N/A'))
            ->line('')
            ->line('Por favor, realiza la inspección de calidad correspondiente y valida o rechaza el trabajo.')
            ->action('🔍 Revisar en Calidad', route('calidad.show', $this->orden))
            ->line('')
            ->line('Es importante realizar esta revisión a la brevedad posible.')
            ->salutation('Atentamente,  
**Sistema de Control de Calidad**');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'OT Lista para Calidad',
            'message' => "La OT #{$this->orden->id} está completada y lista para revisión de calidad.",
            'url' => route('calidad.show', $this->orden),
            'type' => 'ot_lista_calidad',
            'orden_id' => $this->orden->id,
        ];
    }
}
