<?php

// app/Jobs/GenerateOrdenPdf.php
namespace App\Jobs;

use App\Models\Orden;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateOrdenPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $ordenId) {}

    public function handle(): void
    {
        $orden = Orden::with(['servicio','centro','teamLeader','items'])->find($this->ordenId);
        if (!$orden) return;

        $pdf = PDF::loadView('pdf.orden', ['orden'=>$orden])->setPaper('letter');
        $bytes = $pdf->output();

        $path = "pdfs/ordenes/OT_{$orden->id}.pdf";
        Storage::put($path, $bytes);

        $orden->update([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ]);
    }
}

// Cuando despaches el job, usa:
// GenerateOrdenPdf::dispatch($orden->id)->onQueue('pdfs');
