<?php

namespace App\Console\Commands;

use App\Models\Factura;
use App\Notifications\FacturaGeneradaNotification;
use App\Support\Notify;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProbarCorreoFactura extends Command
{
    protected $signature = 'factura:probar-correo {id}';
    protected $description = 'Probar envío de correo de factura con PDF adjunto';

    public function handle()
    {
        $facturaId = $this->argument('id');
        $factura = Factura::with([
            'orden.servicio',
            'orden.centro',
            'orden.solicitud.cliente'
        ])->find($facturaId);

        if (!$factura) {
            $this->error("❌ No se encontró la factura #{$facturaId}");
            return 1;
        }

        $clientesGerentes = Notify::clientGerentesByCenter((int)($factura->orden->id_centrotrabajo ?? 0));

        if ($clientesGerentes->isEmpty()) {
            $this->error("❌ No hay clientes gerentes configurados para el centro de esta factura");
            return 1;
        }

        $cliente = $clientesGerentes->first();

        $this->info("📄 Factura #{$factura->id}");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("   Cliente: {$cliente->name}");
        $this->line("   Email: {$cliente->email}");
        $this->line("   OT: #{$factura->orden->id}");
        $this->line("   Total: $" . number_format($factura->total, 2));
        
        if ($factura->pdf_path && Storage::exists($factura->pdf_path)) {
            $size = Storage::size($factura->pdf_path);
            $this->info("   ✅ PDF: {$factura->pdf_path} (" . number_format($size / 1024, 2) . " KB)");
        } else {
            $this->warn("   ⚠️  PDF no disponible");
        }

        $this->line("");
        $this->info("📧 Enviando correo con PDF adjunto...");

        try {
            Notify::send($clientesGerentes, new FacturaGeneradaNotification($factura));
            $this->line("");
            $this->info("✅ Correo enviado exitosamente!");
            $this->comment('   Destinatarios: ' . $clientesGerentes->pluck('email')->implode(', '));
            $this->line("");
            $this->comment("💡 Revisa tu bandeja de entrada (o Mailtrap si está en modo prueba)");
            $this->comment("   El correo incluye:");
            $this->comment("   • Datos de la factura y orden");
            $this->comment("   • Botón para ver la factura");
            
            if ($factura->pdf_path && Storage::exists($factura->pdf_path)) {
                $this->comment("   • PDF adjunto: Factura_{$factura->id}.pdf");
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error al enviar correo: " . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
}
