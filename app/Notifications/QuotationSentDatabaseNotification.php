<?php

namespace App\Notifications;

use App\Models\Cotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuotationSentDatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Cotizacion $cotizacion,
        public int $itemsCount,
        public ?string $expiresAtIso = null,
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        // Importante: NO guardar token plano en DB.
        return [
            'type' => 'quotation_sent',
            'quotation_id' => (int)$this->cotizacion->id,
            'folio' => (string)$this->cotizacion->folio,
            'total' => (float)($this->cotizacion->total ?? 0),
            'items_count' => (int)$this->itemsCount,
            'expires_at' => $this->expiresAtIso,
            'url' => url("/client/quotations/{$this->cotizacion->id}"),
        ];
    }
}
