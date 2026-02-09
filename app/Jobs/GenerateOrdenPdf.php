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
        // Eager loading completo para evitar N+1 queries
        $orden = Orden::with([
            'servicio',
            'centro',
            'teamLeader',
            'area',
            'items',
            'solicitud.cliente',
            'solicitud.marca',
            'aprobaciones.usuario',
            // Cargar servicios de la OT con todas sus relaciones necesarias
            'otServicios' => function($query) {
                $query->with([
                    'servicio',             // Información del servicio
                    'addedBy',              // Usuario que agregó servicio adicional
                    'items',                // Items del servicio (para totales)
                    'avances' => function($q) {
                        $q->with('createdBy') // Usuario que creó el avance
                          ->orderBy('created_at', 'asc');
                    }
                ])->orderBy('created_at', 'asc');
            }
        ])->find($this->ordenId);
        
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
