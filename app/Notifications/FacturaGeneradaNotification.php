<?php

namespace App\Notifications;

use App\Models\Factura;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

class FacturaGeneradaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Factura $factura
    ) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $orden = $this->factura->orden;
        $servicio = $orden?->servicio?->nombre ?? 'Servicio';
        $centro = $orden?->centro?->nombre ?? 'Centro';

        $mail = (new MailMessage)
            ->subject('📄 Factura #' . $this->factura->id . ' Generada Exitosamente')
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line('Se ha generado exitosamente la **factura** correspondiente a la orden de trabajo que solicitaste.')
            ->line('')
            ->line('**Detalles de la Factura:**')
            ->line('• **Número de Factura:** #' . $this->factura->id)
            ->line('• **Orden de Trabajo:** #' . $orden->id)
            ->line('• **Servicio:** ' . $servicio)
            ->line('• **Centro de Trabajo:** ' . $centro)
            ->line('• **Total:** $' . number_format($this->factura->total, 2) . ' MXN')
            ->line('• **Fecha de Emisión:** ' . $this->factura->created_at->format('d/m/Y'))
            ->line('')
            ->action('📋 Ver Factura Completa', route('facturas.show', $this->factura->id));

        // Adjuntar PDF si existe
        if ($this->factura->pdf_path && Storage::exists($this->factura->pdf_path)) {
            $pdfPath = Storage::path($this->factura->pdf_path);
            $pdfName = "Factura_{$this->factura->id}.pdf";
            
            $mail->attach($pdfPath, [
                'as' => $pdfName,
                'mime' => 'application/pdf',
            ]);
            
            $mail->line('')
                 ->line('📎 **El PDF de la factura está adjunto** a este correo para tu comodidad.');
        } else {
            $mail->line('')
                 ->line('⏳ El PDF se está generando en este momento y estará disponible en breve en el sistema.');
        }

        return $mail->line('')
                    ->line('Gracias por confiar en nuestros servicios.')
                    ->salutation('Atentamente,  
**Equipo Upper Control**');
    }

    public function toDatabase($notifiable)
    {
        $orden = $this->factura->orden;
        
        return [
            'title' => "Factura #{$this->factura->id} generada",
            'message' => "Se generó la factura de la OT #{$orden->id}. Total: $" . number_format($this->factura->total, 2),
            'url' => route('facturas.show', $this->factura->id),
            'factura_id' => $this->factura->id,
            'orden_id' => $orden->id,
        ];
    }
}
