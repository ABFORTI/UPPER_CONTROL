<?php

// app/Jobs/GenerateFacturaPdf.php
namespace App\Jobs;

use App\Models\Factura;
use App\Notifications\FacturaGeneradaNotification;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerateFacturaPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $facturaId,
        public bool $notifyClient = false
    ) {}

    public function handle(): void
    {
        $factura = Factura::with([
            'orden.servicio',
            'orden.centro',
            'orden.items',
            'orden.solicitud.cliente'
        ])->find($this->facturaId);
        
        if (!$factura) return;

        // Parsear XML si existe
        $xml = $this->parseCfdi($factura);

        $pdf = PDF::loadView('pdf.factura', [
            'factura' => $factura,
            'xml' => $xml
        ])->setPaper('letter');
        $bytes = $pdf->output();

        $path = "pdfs/facturas/Factura_{$factura->id}.pdf";
        Storage::put($path, $bytes);

        $factura->update([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ]);

        // Enviar notificación al cliente con el PDF adjunto si se solicita
        if ($this->notifyClient && $factura->orden && $factura->orden->solicitud) {
            $cliente = $factura->orden->solicitud->cliente;
            if ($cliente) {
                $cliente->notify(new FacturaGeneradaNotification($factura));
                Log::info("Notificación de factura enviada al cliente {$cliente->email} con PDF adjunto");
            }
        }
    }

    /**
     * Parsear CFDI XML y extraer datos estructurados
     */
    private function parseCfdi(?Factura $factura): ?array
    {
        if (!$factura || !$factura->xml_path || !Storage::exists($factura->xml_path)) {
            return null;
        }

        try {
            $xmlString = Storage::get($factura->xml_path);
            $xml = simplexml_load_string($xmlString);
            if (!$xml) return null;

            $ns = $xml->getDocNamespaces(true);
            $cfdi = isset($ns['cfdi']) ? $xml->children($ns['cfdi']) : $xml;

            $a = $xml->attributes();
            $data = [];
            $data['version'] = (string)($a['Version'] ?? $a['version'] ?? '');
            $data['serie']   = (string)($a['Serie'] ?? $a['serie'] ?? '');
            $data['folio']   = (string)($a['Folio'] ?? $a['folio'] ?? '');
            $data['fecha']   = (string)($a['Fecha'] ?? $a['fecha'] ?? '');
            $data['subtotal'] = (string)($a['SubTotal'] ?? $a['subTotal'] ?? $a['subtotal'] ?? '');
            $data['descuento'] = (string)($a['Descuento'] ?? $a['descuento'] ?? '');
            $data['total']   = (string)($a['Total'] ?? $a['total'] ?? '');
            $data['forma_pago']   = (string)($a['FormaPago'] ?? $a['formaPago'] ?? '');
            $data['metodo_pago']  = (string)($a['MetodoPago'] ?? $a['metodoPago'] ?? '');
            $data['moneda']  = (string)($a['Moneda'] ?? $a['moneda'] ?? '');
            $data['tipo']    = (string)($a['TipoDeComprobante'] ?? $a['tipoDeComprobante'] ?? '');
            $data['lugar_expedicion'] = (string)($a['LugarExpedicion'] ?? $a['lugarExpedicion'] ?? '');
            $data['no_certificado'] = (string)($a['NoCertificado'] ?? '');
            $data['certificado']    = (string)($a['Certificado'] ?? '');
            $data['sello']          = (string)($a['Sello'] ?? '');

            // Emisor
            if (isset($cfdi->Emisor)) {
                $ea = $cfdi->Emisor->attributes();
                $data['emisor'] = [
                    'rfc' => (string)($ea['Rfc'] ?? $ea['RFC'] ?? $ea['rfc'] ?? ''),
                    'nombre' => (string)($ea['Nombre'] ?? $ea['nombre'] ?? ''),
                    'regimen' => (string)($ea['RegimenFiscal'] ?? $ea['regimenFiscal'] ?? ''),
                ];
            }

            // Receptor
            if (isset($cfdi->Receptor)) {
                $ra = $cfdi->Receptor->attributes();
                $data['receptor'] = [
                    'rfc' => (string)($ra['Rfc'] ?? $ra['RFC'] ?? $ra['rfc'] ?? ''),
                    'nombre' => (string)($ra['Nombre'] ?? $ra['nombre'] ?? ''),
                    'uso' => (string)($ra['UsoCFDI'] ?? $ra['usoCFDI'] ?? ''),
                    'domicilio' => (string)($ra['DomicilioFiscalReceptor'] ?? ''),
                    'regimen' => (string)($ra['RegimenFiscalReceptor'] ?? ''),
                ];
            }

            // Conceptos
            $data['conceptos'] = [];
            if (isset($cfdi->Conceptos) && isset($cfdi->Conceptos->Concepto)) {
                foreach ($cfdi->Conceptos->Concepto as $c) {
                    $ca = $c->attributes();
                    $data['conceptos'][] = [
                        'clave' => (string)($ca['ClaveProdServ'] ?? ''),
                        'cantidad' => (string)($ca['Cantidad'] ?? ''),
                        'clave_unidad' => (string)($ca['ClaveUnidad'] ?? ''),
                        'unidad' => (string)($ca['Unidad'] ?? ''),
                        'descripcion' => (string)($ca['Descripcion'] ?? ''),
                        'valor_unitario' => (string)($ca['ValorUnitario'] ?? ''),
                        'importe' => (string)($ca['Importe'] ?? ''),
                    ];
                }
            }

            // Impuestos
            $data['impuestos'] = [
                'trasladados' => [],
                'retenciones' => [],
                'total_trasladados' => null,
                'total_retenciones' => null
            ];
            
            if (isset($cfdi->Impuestos)) {
                $ia = $cfdi->Impuestos->attributes();
                $data['impuestos']['total_trasladados'] = (string)($ia['TotalImpuestosTrasladados'] ?? '');
                $data['impuestos']['total_retenciones'] = (string)($ia['TotalImpuestosRetenidos'] ?? '');
                
                if (isset($cfdi->Impuestos->Traslados) && isset($cfdi->Impuestos->Traslados->Traslado)) {
                    foreach ($cfdi->Impuestos->Traslados->Traslado as $t) {
                        $ta = $t->attributes();
                        $data['impuestos']['trasladados'][] = [
                            'impuesto' => (string)($ta['Impuesto'] ?? ''),
                            'tasa' => (string)($ta['TasaOCuota'] ?? ''),
                            'importe' => (string)($ta['Importe'] ?? ''),
                            'base' => (string)($ta['Base'] ?? ''),
                            'tipo_factor' => (string)($ta['TipoFactor'] ?? ''),
                        ];
                    }
                }
            }

            // Timbre Fiscal Digital
            $data['uuid'] = null;
            $data['fecha_timbrado'] = null;
            $data['no_cert_sat'] = null;
            $data['sello_sat'] = null;
            
            $tfdNode = null;
            if (isset($ns['tfd'])) {
                $complemento = $cfdi->Complemento ?? null;
                if ($complemento) {
                    foreach ($complemento->children($ns['tfd']) as $child) {
                        if ($child->getName() === 'TimbreFiscalDigital') {
                            $tfdNode = $child;
                            break;
                        }
                    }
                }
            }
            
            if (!$tfdNode) {
                $nodes = $xml->xpath('//*[local-name()="TimbreFiscalDigital"]');
                if (!empty($nodes)) {
                    $tfdNode = $nodes[0];
                }
            }
            
            if ($tfdNode) {
                $ta = $tfdNode->attributes();
                $data['uuid'] = (string)($ta['UUID'] ?? $ta['Uuid'] ?? '');
                $data['fecha_timbrado'] = (string)($ta['FechaTimbrado'] ?? '');
                $data['no_cert_sat'] = (string)($ta['NoCertificadoSAT'] ?? '');
                $data['sello_sat'] = (string)($ta['SelloSAT'] ?? '');
            }

            // Generar código QR si tenemos UUID
            if (!empty($data['uuid']) && !empty($data['emisor']['rfc']) && !empty($data['receptor']['rfc'])) {
                $this->generateQrCode($data);
            }

            return $data;

        } catch (\Exception $e) {
            Log::error('Error parseando XML de factura: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generar código QR para verificación SAT
     */
    private function generateQrCode(array &$data): void
    {
        try {
            $rfcEm = strtoupper(trim($data['emisor']['rfc'] ?? ''));
            $rfcRe = strtoupper(trim($data['receptor']['rfc'] ?? ''));
            $total = $data['total'] ?? '0';
            $uuid = strtoupper(trim($data['uuid'] ?? ''));

            if (!$rfcEm || !$rfcRe || !$uuid) return;

            // Formatear total con formato SAT (17 caracteres: 10 enteros + punto + 6 decimales)
            $totalFloat = (float)$total;
            $totalFormatted = $this->formatTotalSat($total);
            
            // Formatear total alternativo (sin padding)
            $totalDefault = $this->formatTotalDefault($total);

            // Últimos 8 caracteres de los sellos
            $selloUltimos8 = !empty($data['sello']) ? substr($data['sello'], -8) : '';
            $selloSatUltimos8 = !empty($data['sello_sat']) ? substr($data['sello_sat'], -8) : '';
            $fe = $selloUltimos8 ?: $selloSatUltimos8;

            // URLs de verificación SAT
            $baseConsulta = 'https://verificacfdi.facturaelectronica.sat.gob.mx/Consulta/qr';
            $baseDefault = 'https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx';
            
            $queryConsulta = sprintf('id=%s&re=%s&rr=%s&tt=%s%s',
                $uuid, $rfcEm, $rfcRe, $totalFormatted, $fe ? ('&fe=' . rawurlencode($fe)) : ''
            );
            
            $queryDefault = sprintf('id=%s&re=%s&rr=%s&tt=%s%s',
                $uuid, $rfcEm, $rfcRe, $totalDefault, ($fe !== null && $fe !== '') ? ('&fe=' . $fe) : ''
            );

            $urlConsulta = $baseConsulta . '?' . $queryConsulta;
            $urlDefault = $baseDefault . '?' . $queryDefault;
            
            // Guardar URLs
            $data['sat_qr_url_consulta'] = $urlConsulta;
            $data['sat_qr_url_default'] = $urlDefault;
            $data['sat_qr_target'] = $urlDefault;

            // Generar QR como imagen PNG (igual que en FacturaController)
            $clazz = '\\SimpleSoftwareIO\\QrCode\\Facades\\QrCode';
            if (class_exists($clazz)) {
                // Intentar generar PNG (requiere imagick o gd)
                try {
                    $png = $clazz::format('png')
                        ->errorCorrection('H')
                        ->size(256)
                        ->margin(1)
                        ->generate($urlDefault);
                    $data['sat_qr_png'] = 'data:image/png;base64,' . base64_encode($png);
                } catch (\Throwable $e) {
                    // Si falla PNG, intentar con SVG (no requiere extensiones)
                    try {
                        $svg = $clazz::format('svg')
                            ->errorCorrection('H')
                            ->size(256)
                            ->margin(1)
                            ->generate($urlDefault);
                        $data['sat_qr_svg_datauri'] = 'data:image/svg+xml;base64,' . base64_encode($svg);
                        // Guardar también como PNG para compatibilidad
                        $data['sat_qr_png'] = $data['sat_qr_svg_datauri'];
                    } catch (\Throwable $e2) {
                        Log::warning('Error generando QR: ' . $e2->getMessage());
                        $data['sat_qr_png'] = null;
                    }
                }
                
                // Generar SVG adicional
                try {
                    $svg = $clazz::format('svg')
                        ->errorCorrection('H')
                        ->size(256)
                        ->margin(1)
                        ->generate($urlDefault);
                    $data['sat_qr_svg_datauri'] = 'data:image/svg+xml;base64,' . base64_encode($svg);
                } catch (\Throwable $e) {
                    Log::warning('Error generando QR SVG: ' . $e->getMessage());
                    $data['sat_qr_svg_datauri'] = null;
                }
            } else {
                $data['sat_qr_png'] = null;
                $data['sat_qr_svg_datauri'] = null;
            }

        } catch (\Exception $e) {
            Log::warning('No se pudo generar QR: ' . $e->getMessage());
        }
    }

    /**
     * Formatea el total para el parámetro tt del SAT (formato: 0000000000.000000)
     */
    private function formatTotalSat($total): ?string
    {
        if ($total === null || $total === '') return null;
        $s = str_replace(',', '.', trim((string)$total));
        $s = preg_replace('/[^0-9.]/', '', $s);
        if (empty($s)) return null;
        $f = (float)$s;
        $intPart = (int)$f;
        $decPart = $f - $intPart;
        $intStr = str_pad((string)$intPart, 10, '0', STR_PAD_LEFT);
        $decStr = number_format($decPart, 6, '.', '');
        $decStr = substr($decStr, 1); // quitar el "0"
        return $intStr . $decStr;
    }

    /**
     * Formatea el total alternativo (sin padding)
     */
    private function formatTotalDefault($total): ?string
    {
        if ($total === null || $total === '') return null;
        $s = str_replace(',', '.', trim((string)$total));
        $s = preg_replace('/[^0-9.]/', '', $s);
        if (empty($s)) return null;
        $f = (float)$s;
        return number_format($f, 6, '.', '');
    }
}
