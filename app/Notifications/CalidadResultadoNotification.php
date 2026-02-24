<?php

// app/Notifications/CalidadResultadoNotification.php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Orden;

class CalidadResultadoNotification extends Notification {
  use Queueable;
  
  public function __construct(
    public Orden $orden, 
    public string $resultado, 
    public ?string $obs = null
  ) {}
  
  public function via($notifiable){ return ['database', 'mail']; }

  public function toDatabase($notifiable): array
  {
    $esAprobado = strtolower($this->resultado) === 'aprobado' || strtolower($this->resultado) === 'validado';
    $titulo = $esAprobado ? 'Calidad Aprobada' : 'Calidad Rechazada';
    $icono = $esAprobado ? '✅' : '❌';

    return [
      'title'    => "{$icono} {$titulo}",
      'message'  => "OT #{$this->orden->id}: revisión de calidad — {$this->resultado}." . ($this->obs ? " Obs: {$this->obs}" : ''),
      'url'      => route('ordenes.show', $this->orden->id),
      'type'     => 'calidad_resultado',
      'orden_id' => $this->orden->id,
    ];
  }

  public function toMail($notifiable){
    $esAprobado = strtolower($this->resultado) === 'aprobado' || strtolower($this->resultado) === 'validado';
    $icono = $esAprobado ? '✅' : '❌';
    $titulo = $esAprobado ? 'Aprobada' : 'Rechazada';

    $msg = (new MailMessage)
      ->subject($icono . ' Revisión de Calidad: ' . $titulo . ' - OT #' . $this->orden->id)
      ->greeting('¡Hola ' . $notifiable->name . '!')
      ->line('La revisión de calidad para la **Orden de Trabajo #' . $this->orden->id . '** ha sido completada.')
      ->line('')
      ->line('**Resultado de Calidad:**')
      ->line('• **Estado:** ' . ($esAprobado ? '✅ **APROBADO**' : '❌ **RECHAZADO**'))
      ->line('• **OT:** #' . $this->orden->id)
      ->line('• **Servicio:** ' . ($this->orden->servicio?->nombre ?? 'N/A'))
      ->line('• **Centro:** ' . ($this->orden->centro?->nombre ?? 'N/A'));

    if ($this->obs) {
      $msg->line('')
          ->line('**Observaciones de Calidad:**')
          ->line('_' . $this->obs . '_');
    }

    $msg->line('')
        ->action('📋 Ver Orden de Trabajo', route('ordenes.show', $this->orden->id))
        ->line('');

    if ($esAprobado) {
      $msg->line('La orden ha pasado el control de calidad y puede continuar con el proceso.');
    } else {
      $msg->line('⚠️ La orden no pasó el control de calidad. Por favor, revisa las observaciones y realiza las correcciones necesarias.');
    }

    return $msg->salutation('Saludos,  
**Departamento de Calidad**');
  }
}
