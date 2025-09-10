<?php

namespace App\Exports;

use App\Models\Orden;
use Illuminate\Contracts\Auth\Authenticatable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class OtExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithColumnFormatting, WithChunkReading
{
    public function __construct(
        protected array $filters,
        protected Authenticatable $user
    ) {}

    public function query()
    {
        $u = $this->user;
        $f = $this->filters;

        $q = Orden::query()
            ->with(['servicio','centro','teamLeader','solicitud.cliente'])
            // visibilidad por rol
            ->when(!$u->hasAnyRole(['admin','facturacion']), fn($qq) =>
                $qq->where('id_centrotrabajo', $u->centro_trabajo_id)
            )
            ->when($u->hasRole('team_leader'), fn($qq) =>
                $qq->where('team_leader_id', $u->id)
            )
            ->when($u->hasRole('cliente'), fn($qq) =>
                $qq->whereHas('solicitud', fn($w)=>$w->where('id_cliente', $u->id))
            )
            // filtros
            ->when(data_get($f,'id'),        fn($qq,$v)=>$qq->where('id',$v))
            ->when(data_get($f,'estatus'),   fn($qq,$v)=>$qq->where('estatus',$v))
            ->when(data_get($f,'calidad'),   fn($qq,$v)=>$qq->where('calidad_resultado',$v))
            ->when(data_get($f,'servicio'),  fn($qq,$v)=>$qq->where('id_servicio',$v))
            ->when(data_get($f,'centro'),    fn($qq,$v)=>$qq->where('id_centrotrabajo',$v))
            ->when(data_get($f,'tl'),        fn($qq,$v)=>$qq->where('team_leader_id',$v))
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
            'OT', 'Fecha', 'Centro', 'Servicio', 'Team Leader', 'Cliente',
            'Estatus', 'Calidad', 'Total Planeado', 'Total Real'
        ];
    }

    public function map($o): array
    {
        return [
            $o->id,
            optional($o->created_at)->format('Y-m-d H:i'),
            $o->centro->nombre ?? '—',
            $o->servicio->nombre ?? '—',
            $o->teamLeader->name ?? '—',
            $o->solicitud?->cliente?->name ?? '—',
            $o->estatus,
            $o->calidad_resultado,
            (float) $o->total_planeado,
            (float) $o->total_real,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'I' => NumberFormat::FORMAT_NUMBER_00, // Total Planeado
            'J' => NumberFormat::FORMAT_NUMBER_00, // Total Real
        ];
    }

    public function chunkSize(): int
    {
        return 500; // export robusto
    }
}
