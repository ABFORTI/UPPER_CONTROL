<?php

namespace App\Console\Commands;

use App\Models\Factura;
use App\Jobs\GenerateFacturaPdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RegenerarFacturaPdf extends Command
{
    protected $signature = 'factura:regenerar-pdf {id}';
    protected $description = 'Regenerar el PDF de una factura con los datos del XML';

    public function handle()
    {
        $facturaId = $this->argument('id');
        $factura = Factura::with(['orden.servicio', 'orden.centro', 'orden.items'])->find($facturaId);

        if (!$factura) {
            $this->error("❌ No se encontró la factura #{$facturaId}");
            return 1;
        }

        $this->info("📄 Factura #{$factura->id}");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // Verificar XML
        if (!$factura->xml_path || !Storage::exists($factura->xml_path)) {
            $this->warn("⚠️  Esta factura no tiene XML cargado");
            $this->line("   El PDF se generará solo con los datos de la orden");
        } else {
            $this->info("✅ XML disponible: {$factura->xml_path}");
            
            // Mostrar algunos datos del XML
            $xmlString = Storage::get($factura->xml_path);
            $xml = simplexml_load_string($xmlString);
            
            if ($xml) {
                $ns = $xml->getDocNamespaces(true);
                $cfdi = isset($ns['cfdi']) ? $xml->children($ns['cfdi']) : $xml;
                
                $this->line("");
                $this->line("📋 Datos del XML que se incluirán en el PDF:");
                
                // Datos principales
                $attrs = $xml->attributes();
                $this->line("   • Serie: " . ($attrs['Serie'] ?? '—'));
                $this->line("   • Folio: " . ($attrs['Folio'] ?? '—'));
                $this->line("   • Total: $" . number_format((float)($attrs['Total'] ?? 0), 2));
                
                // Emisor
                if (isset($cfdi->Emisor)) {
                    $emisor = $cfdi->Emisor->attributes();
                    $this->line("   • Emisor: " . ($emisor['Nombre'] ?? '—'));
                    $this->line("   • RFC Emisor: " . ($emisor['Rfc'] ?? '—'));
                }
                
                // Receptor
                if (isset($cfdi->Receptor)) {
                    $receptor = $cfdi->Receptor->attributes();
                    $this->line("   • Receptor: " . ($receptor['Nombre'] ?? '—'));
                    $this->line("   • RFC Receptor: " . ($receptor['Rfc'] ?? '—'));
                }
                
                // UUID
                $timbres = $xml->xpath('//*[local-name()="TimbreFiscalDigital"]');
                if (!empty($timbres)) {
                    $timbre = $timbres[0]->attributes();
                    $this->line("   • UUID: " . ($timbre['UUID'] ?? '—'));
                }
                
                // Conceptos
                if (isset($cfdi->Conceptos) && isset($cfdi->Conceptos->Concepto)) {
                    $count = count($cfdi->Conceptos->Concepto);
                    $this->line("   • Conceptos: $count concepto(s)");
                }
            }
        }

        $this->line("");
        $this->info("🔄 Regenerando PDF...");
        
        // Eliminar PDF anterior si existe
        if ($factura->pdf_path && Storage::exists($factura->pdf_path)) {
            Storage::delete($factura->pdf_path);
            $this->line("   Eliminado PDF anterior");
        }

        // Generar nuevo PDF
        GenerateFacturaPdf::dispatchSync($factura->id);
        $factura->refresh();

        if ($factura->pdf_path && Storage::exists($factura->pdf_path)) {
            $size = Storage::size($factura->pdf_path);
            $this->line("");
            $this->info("✅ PDF generado exitosamente!");
            $this->line("   📁 Ruta: {$factura->pdf_path}");
            $this->line("   📏 Tamaño: " . number_format($size / 1024, 2) . " KB");
            $this->line("   🕐 Generado: {$factura->pdf_generated_at}");
            $this->line("");
            $this->line("📂 Ubicación completa: storage/app/{$factura->pdf_path}");
            $this->line("");
            $this->comment("💡 Los datos del XML ahora están en el PDF");
            
            return 0;
        } else {
            $this->error("❌ Error al generar el PDF");
            return 1;
        }
    }
}
