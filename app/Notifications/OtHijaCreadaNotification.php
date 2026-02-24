<?php

namespace App\Notifications;

use App\Models\Orden;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OtHijaCreadaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Orden $otPadre,
        public Orden $otHija,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🔀 Nueva OT Hija Creada - OT #' . $this->otHija->id)
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line('Se ha creado una **OT Hija** a partir de un corte de la OT origen.')
            ->line('')
            ->line('**Detalles:**')
            ->line('• **OT Origen:** #' . $this->otPadre->id)
            ->line('• **OT Hija:** #' . $this->otHija->id)
            ->line('• **Centro de Trabajo:** ' . ($this->otHija->centro?->nombre ?? 'N/A'))
            ->line('• **Servicio:** ' . ($this->otHija->servicio?->nombre ?? 'Múltiple'))
            ->line('')
            ->line('La OT hija contiene el remanente del corte y requiere gestión.')
            ->action('📋 Ver OT Hija', route('ordenes.show', $this->otHija->id))
            ->salutation('Saludos,  
**Equipo Upper Control**');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title'         => 'OT Hija Creada',
            'message'       => "OT Hija #{$this->otHija->id} creada a partir de OT #{$this->otPadre->id}.",
            'url'           => route('ordenes.show', $this->otHija->id),
            'type'          => 'ot_hija_creada',
            'orden_id'      => $this->otHija->id,
            'orden_padre_id'=> $this->otPadre->id,
        ];
    }
}
