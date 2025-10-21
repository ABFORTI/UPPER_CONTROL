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
            ->subject('✅ OT #' . $this->orden->id . ' - Validación de Calidad Completada')
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line('Excelentes noticias. La **Orden de Trabajo #' . $this->orden->id . '** ha sido **validada exitosamente** por el departamento de calidad.')
            ->line('')
            ->line('**Detalles de la OT:**')
            ->line('• **Número:** #' . $this->orden->id)
            ->line('• **Servicio:** ' . ($this->orden->servicio?->nombre ?? 'N/A'))
            ->line('• **Centro de Trabajo:** ' . ($this->orden->centro?->nombre ?? 'N/A'))
            ->line('• **Estado:** ✅ Validada por Calidad')
            ->line('')
            ->line('**🎯 Acción Requerida:**')
            ->line('Como cliente/autorizado, necesitas **revisar y autorizar** esta orden para que pueda continuar al proceso de facturación.')
            ->line('')
            ->action('✅ Autorizar Orden de Trabajo', route('ordenes.show', $this->orden))
            ->line('')
            ->line('Tu autorización es el último paso antes de la facturación.')
            ->salutation('Gracias por tu atención,  
**Equipo Upper Control**');
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
