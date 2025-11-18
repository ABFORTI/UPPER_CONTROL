<?php

namespace App\Notifications;

use App\Models\Orden;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecordatorioValidacionOt extends Notification
{
    use Queueable;

    public function __construct(
        public Orden $orden,
        public int $horasEspera
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $dias = floor($this->horasEspera / 24);
        $horas = $this->horasEspera % 24;
        
        $tiempoTexto = '';
        if ($dias > 0) {
            $tiempoTexto = $dias . ' ' . ($dias === 1 ? 'día' : 'días');
            if ($horas > 0) {
                $tiempoTexto .= ' y ' . $horas . ' ' . ($horas === 1 ? 'hora' : 'horas');
            }
        } else {
            $tiempoTexto = $horas . ' ' . ($horas === 1 ? 'hora' : 'horas');
        }

        return (new MailMessage)
            ->subject('⏰ Recordatorio Urgente: OT #' . $this->orden->id . ' Pendiente de Autorización')
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line('Te enviamos este **recordatorio** sobre una orden de trabajo que está **pendiente de tu autorización**.')
            ->line('')
            ->line('**📋 Detalles de la OT:**')
            ->line('• **Número:** #' . $this->orden->id)
            ->line('• **Servicio:** ' . ($this->orden->servicio?->nombre ?? 'N/A'))
            ->line('• **Centro de Trabajo:** ' . ($this->orden->centro?->nombre ?? 'N/A'))
            ->line('• **Estado:** ✅ Validada por Calidad')
            ->line('')
            ->line('⏱️ **Tiempo en espera:** ' . $tiempoTexto)
            ->line('')
            ->line('La orden fue **completada y validada** por el departamento de calidad. Para continuar con el proceso de facturación, necesitamos tu autorización.')
            ->line('')
            ->action('✅ Autorizar Orden Ahora', route('ordenes.show', $this->orden))
            ->line('')
            ->line('⚠️ Es importante que **autorices esta orden** lo antes posible para no retrasar el proceso.')
            ->salutation('Gracias por tu atención,  
**Equipo Upper Control**');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Recordatorio de Autorización',
            'message' => "La OT #{$this->orden->id} lleva {$this->horasEspera}h esperando tu autorización. Por favor revísala.",
            'url' => route('ordenes.show', $this->orden),
            'type' => 'recordatorio_validacion',
            'orden_id' => $this->orden->id,
            'horas_espera' => $this->horasEspera,
        ];
    }
}
