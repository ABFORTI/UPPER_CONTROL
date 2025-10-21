<?php

namespace App\Console\Commands;

use App\Models\Factura;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class VerificarFacturaPdf extends Command
{
    protected $signature = 'factura:verificar-pdf {id}';
    protected $description = 'Verificar que el PDF de una factura contenga los datos del XML';

    public function handle()
    {
        $facturaId = $this->argument('id');
        $factura = Factura::with(['orden.servicio', 'orden.centro'])->find($facturaId);

        if (!$factura) {
            $this->error("❌ No se encontró la factura #{$facturaId}");
            return 1;
        }

        $this->info("📄 Verificando Factura #{$factura->id}");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // Verificar XML
        if (!$factura->xml_path) {
            $this->warn("⚠️  No tiene XML cargado");
        } else {
            if (Storage::exists($factura->xml_path)) {
                $this->info("✅ XML existe: {$factura->xml_path}");
                
                // Parsear XML para mostrar datos
                $xmlString = Storage::get($factura->xml_path);
                $xml = simplexml_load_string($xmlString);
                
                if ($xml) {
                    $attrs = $xml->attributes();
                    $this->line("");
                    $this->line("📋 Datos del XML:");
                    $this->line("   Serie: " . ($attrs['Serie'] ?? '—'));
                    $this->line("   Folio: " . ($attrs['Folio'] ?? '—'));
                    $this->line("   Total: $" . number_format((float)($attrs['Total'] ?? 0), 2));
                    $this->line("   Fecha: " . ($attrs['Fecha'] ?? '—'));
                    
                    // Emisor
                    if (isset($xml->Emisor)) {
                        $emisor = $xml->Emisor->attributes();
                        $this->line("   Emisor: " . ($emisor['Nombre'] ?? '—'));
                        $this->line("   RFC Emisor: " . ($emisor['Rfc'] ?? '—'));
                    }
                    
                    // Receptor
                    if (isset($xml->Receptor)) {
                        $receptor = $xml->Receptor->attributes();
                        $this->line("   Receptor: " . ($receptor['Nombre'] ?? '—'));
                        $this->line("   RFC Receptor: " . ($receptor['Rfc'] ?? '—'));
                    }
                    
                    // UUID
                    $nodes = $xml->xpath('//*[local-name()="TimbreFiscalDigital"]');
                    if (!empty($nodes)) {
                        $timbre = $nodes[0]->attributes();
                        $this->line("   UUID: " . ($timbre['UUID'] ?? '—'));
                    }
                }
            } else {
                $this->error("❌ El archivo XML no existe en storage");
            }
        }

        // Verificar PDF
        $this->line("");
        if (!$factura->pdf_path) {
            $this->warn("⚠️  No tiene PDF generado");
            $this->line("");
            $this->ask("¿Deseas generar el PDF ahora? (presiona Enter)");
            
            $this->info("🔄 Generando PDF...");
            \App\Jobs\GenerateFacturaPdf::dispatchSync($factura->id);
            $factura->refresh();
            $this->info("✅ PDF generado: {$factura->pdf_path}");
        } else {
            if (Storage::exists($factura->pdf_path)) {
                $size = Storage::size($factura->pdf_path);
                $this->info("✅ PDF existe: {$factura->pdf_path}");
                $this->line("   Tamaño: " . number_format($size / 1024, 2) . " KB");
                $this->line("   Generado: " . ($factura->pdf_generated_at ?? 'Desconocido'));
                $this->line("");
                $this->line("📁 Ubicación completa: storage/app/{$factura->pdf_path}");
            } else {
                $this->error("❌ El archivo PDF no existe en storage");
            }
        }

        return 0;
    }
}
