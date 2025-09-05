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
            ->subject('OT autorizada por cliente')
            ->line("La OT #{$this->orden->id} fue autorizada por el cliente.")
            ->action('Facturar', route('facturas.createFromOrden', $this->orden));
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
