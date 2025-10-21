<?php

namespace App\Notifications;

use App\Models\Orden;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OtAutorizadaParaFacturacion extends Notification
{
    use Queueable;

    public function __construct(public Orden $orden) {}

    public function via($notifiable): array { return ['database','mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('💰 OT #' . $this->orden->id . ' Lista para Facturación')
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line('Una **Orden de Trabajo** ha sido **autorizada por el cliente** y está lista para ser facturada.')
            ->line('')
            ->line('**Detalles de la OT:**')
            ->line('• **Número:** #' . $this->orden->id)
            ->line('• **Servicio:** ' . ($this->orden->servicio?->nombre ?? 'N/A'))
            ->line('• **Centro de Trabajo:** ' . ($this->orden->centro?->nombre ?? 'N/A'))
            ->line('• **Estado:** ✅ Autorizada - Lista para Facturar')
            ->line('')
            ->line('**🎯 Acción Requerida:**')
            ->line('Por favor, procede a generar la factura correspondiente.')
            ->line('')
            ->action('💵 Generar Factura', route('facturas.createFromOrden', $this->orden))
            ->line('')
            ->line('Todos los pasos previos han sido completados exitosamente.')
            ->salutation('Saludos,  
**Departamento de Facturación**');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'ot_autorizada_facturacion',
            'orden_id' => $this->orden->id,
            'mensaje' => "OT #{$this->orden->id} autorizada por cliente (facturar).",
            'url' => route('facturas.createFromOrden', $this->orden),
        ];
    }
}
