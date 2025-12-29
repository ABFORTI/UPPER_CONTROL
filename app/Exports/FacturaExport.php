<?php

namespace App\Exports;

use App\Models\Factura;
use Illuminate\Contracts\Auth\Authenticatable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class FacturaExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithColumnFormatting, WithChunkReading
{
    public function __construct(
        protected array $filters,
        protected Authenticatable $user
    ) {}

    public function query()
    {
        $u = $this->user;
        $f = $this->filters;

        $q = Factura::query()
            ->with(['orden.servicio','orden.centro','orden.solicitud.cliente'])
            // visibilidad por rol (supervisor (antes 'cliente') ve solo sus facturas)
            ->when($u->hasRole('supervisor'), fn($qq) =>
                $qq->whereHas('orden.solicitud', fn($w)=>$w->where('id_cliente',$u->id))
            )
            ->when(!$u->hasAnyRole(['admin','facturacion']), fn($qq) =>
                $qq->whereHas('orden', fn($w)=>$w->where('id_centrotrabajo',$u->centro_trabajo_id))
            )
            // filtros
            ->when(data_get($f,'estatus'), fn($qq,$v)=>$qq->where('estatus',$v))
            ->when(data_get($f,'centro'),  fn($qq,$v)=>$qq->whereHas('orden', fn($w)=>$w->where('id_centrotrabajo',$v)))
            ->when(data_get($f,'servicio'),fn($qq,$v)=>$qq->whereHas('orden', fn($w)=>$w->where('id_servicio',$v)))
            ->when(data_get($f,'desde') && data_get($f,'hasta'), function($qq) use ($f) {
                $qq->whereBetween('created_at', [
                    \Illuminate\Support\Carbon::parse($f['desde'])->startOfDay(),
                    \Illuminate\Support\Carbon::parse($f['hasta'])->endOfDay(),
                ]);
            })
            ->orderBy('id');

        return $q;
    }

    public function headings(): array
    {
        return [
            'Factura', 'Fecha', 'Estatus', 'Total',
            'OT', 'Centro', 'Servicio', 'Cliente',
        ];
    }

    public function map($f): array
    {
        $o = $f->orden;
        return [
            $f->id,
            optional($f->created_at)->format('Y-m-d H:i'),
            $f->estatus,
            (float) $f->total,
            $o?->id,
            $o?->centro?->nombre ?? '—',
            $o?->servicio?->nombre ?? '—',
            $o?->solicitud?->cliente?->name ?? '—',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_NUMBER_00, // Total
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
