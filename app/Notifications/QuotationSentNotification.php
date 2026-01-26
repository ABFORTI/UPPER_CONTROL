<?php

namespace App\Notifications;

use App\Models\Cotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotationSentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Cotizacion $cotizacion,
        public string $plainToken,
        public int $itemsCount,
        public ?string $expiresAtIso = null,
    ) {
    }

    public function via($notifiable): array
    {
        // Si enviamos a un destinatario específico vía AnonymousNotifiable,
        // no intentamos guardar notificación en DB.
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }

        return ['database', 'mail'];
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

    public function toMail($notifiable): MailMessage
    {
        $reviewUrl = route('client.public.quotations.show', [
            'cotizacion' => $this->cotizacion->id,
            'token' => $this->plainToken,
        ]);

        return (new MailMessage)
            ->subject('📨 Nueva cotización ' . $this->cotizacion->folio)
            ->markdown('emails.quotations.sent', [
                'notifiable' => $notifiable,
                'cotizacion' => $this->cotizacion,
                'itemsCount' => $this->itemsCount,
                'reviewUrl' => $reviewUrl,
                'expiresAtIso' => $this->expiresAtIso,
            ]);
    }
}
