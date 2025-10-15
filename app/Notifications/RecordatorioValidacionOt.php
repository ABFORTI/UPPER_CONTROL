<?php

namespace App\Notifications;

use App\Models\Orden;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecordatorioValidacionOt extends Notification implements ShouldQueue
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
            ->subject("⏰ Recordatorio: OT #{$this->orden->id} Pendiente de Autorización")
            ->greeting("Hola {$notifiable->name},")
            ->line("Te recordamos que tienes una orden de trabajo pendiente de autorización.")
            ->line("**Orden:** #{$this->orden->id}")
            ->line("**Servicio:** {$this->orden->servicio?->nombre}")
            ->line("**Centro:** {$this->orden->centro?->nombre}")
            ->line("**Tiempo en espera:** {$tiempoTexto}")
            ->line("La orden fue completada y validada por calidad. Por favor, revísala y autorízala para continuar con el proceso de facturación.")
            ->action('Autorizar Orden', route('ordenes.show', $this->orden))
            ->line('Es importante que autorices esta orden lo antes posible.')
            ->salutation('Saludos, Upper Control');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'recordatorio_validacion',
            'orden_id' => $this->orden->id,
            'horas_espera' => $this->horasEspera,
            'mensaje' => "Recordatorio: OT #{$this->orden->id} pendiente de autorización ({$this->horasEspera}h en espera)",
            'url' => route('ordenes.show', $this->orden),
        ];
    }
}
