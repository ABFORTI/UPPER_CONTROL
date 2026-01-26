<?php

namespace App\Notifications;

use App\Models\Cotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotationApprovedCoordinatorNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Cotizacion $cotizacion,
        public int $solicitudesCount,
    ) {
    }

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (config('business.notify_coordinator_email_on_quotation_approved', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase($notifiable): array
    {
        $folio = $this->cotizacion->folio ?? ('#' . $this->cotizacion->id);
        return [
            // Formato estándar usado en campanita (SystemEventNotification)
            'title' => 'Cotización autorizada',
            'message' => "El cliente autorizó la cotización {$folio}. Solicitudes generadas: " . (int)$this->solicitudesCount,
            'url' => route('cotizaciones.show', $this->cotizacion->id),

            // Datos extra
            'type' => 'quotation_approved',
            'quotation_id' => (int)$this->cotizacion->id,
            'folio' => (string)($this->cotizacion->folio ?? ''),
            'solicitudes_count' => (int)$this->solicitudesCount,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $folio = $this->cotizacion->folio ?? ('#' . $this->cotizacion->id);

        return (new MailMessage)
            ->subject('✅ Cotización autorizada: ' . $folio)
            ->greeting('¡Hola ' . ($notifiable->name ?? 'usuario') . '!')
            ->line("El cliente autorizó la cotización {$folio}.")
            ->line('Solicitudes generadas: ' . (int)$this->solicitudesCount)
            ->action('Ver cotización', route('cotizaciones.show', $this->cotizacion->id));
    }
}
