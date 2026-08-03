<?php

namespace App\Notifications;

use App\Models\Orden;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtListaParaAutorizacionCliente extends Notification
{
    use Queueable;

    public function __construct(public Orden $orden) {}

    public function via($notifiable): array { return ['database','mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('✅ OT #' . $this->orden->id . ' lista para autorización')
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line('La **Orden de Trabajo #' . $this->orden->id . '** ya está lista para tu autorización.')
            ->line('')
            ->line('**Detalles de la OT:**')
            ->line('• **Número:** #' . $this->orden->id)
            ->line('• **Servicio:** ' . ($this->orden->servicio?->nombre ?? 'N/A'))
            ->line('• **Centro de Trabajo:** ' . ($this->orden->centro?->nombre ?? 'N/A'))
            ->line('• **Estado:** Lista para autorización del cliente')
            ->line('')
            ->line('Para este almacén, la revisión de calidad se omite por configuración y la orden pasa directo a autorización del cliente.')
            ->action('✅ Revisar y autorizar OT', route('ordenes.show', $this->orden))
            ->line('')
            ->line('Tu autorización es necesaria para continuar con el proceso de facturación.')
            ->salutation("Gracias por tu atención,\n**Equipo Upper Control**");
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'OT lista para autorización',
            'message' => "La OT #{$this->orden->id} ya está lista para tu autorización. En este almacén se omitió la revisión de calidad por configuración.",
            'url' => route('ordenes.show', $this->orden),
            'type' => 'ot_lista_autorizacion_cliente',
            'orden_id' => $this->orden->id,
        ];
    }
}
