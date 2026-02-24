<?php

namespace App\Notifications;

use App\Models\OtCorte;
use App\Models\Orden;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OtCorteGeneradoNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Orden $orden,
        public OtCorte $corte,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('✂️ Corte de OT Generado - OT #' . $this->orden->id)
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line('Se ha generado un **corte** para la Orden de Trabajo.')
            ->line('')
            ->line('**Detalles del Corte:**')
            ->line('• **OT Origen:** #' . $this->orden->id)
            ->line('• **Folio Corte:** ' . $this->corte->folio_corte)
            ->line('• **Período:** ' . $this->corte->periodo_inicio . ' → ' . $this->corte->periodo_fin)
            ->line('• **Monto Total:** $' . number_format($this->corte->monto_total, 2))
            ->line('• **Estatus:** ' . $this->corte->estatus)
            ->line('')
            ->action('📋 Ver Orden de Trabajo', route('ordenes.show', $this->orden->id))
            ->line('Este corte está listo para facturación.')
            ->salutation('Saludos,  
**Equipo Upper Control**');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title'    => 'Corte de OT Generado',
            'message'  => "Corte {$this->corte->folio_corte} generado para OT #{$this->orden->id} por \${$this->corte->monto_total}.",
            'url'      => route('ordenes.show', $this->orden->id),
            'type'     => 'ot_corte_generado',
            'orden_id' => $this->orden->id,
            'corte_id' => $this->corte->id,
        ];
    }
}
