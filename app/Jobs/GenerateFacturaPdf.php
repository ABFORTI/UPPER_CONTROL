<?php

// app/Jobs/GenerateFacturaPdf.php
namespace App\Jobs;

use App\Models\Factura;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateFacturaPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $facturaId) {}

    public function handle(): void
    {
        $factura = Factura::with(['orden.servicio','orden.centro','orden.items'])->find($this->facturaId);
        if (!$factura) return;

        $pdf = PDF::loadView('pdf.factura', ['factura'=>$factura])->setPaper('letter');
        $bytes = $pdf->output();

        $path = "pdfs/facturas/Factura_{$factura->id}.pdf";
        Storage::put($path, $bytes);

        $factura->update([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ]);
    }
}
