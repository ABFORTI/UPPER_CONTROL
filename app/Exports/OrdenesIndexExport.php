<?php

namespace App\Exports;

use App\Models\CentroCosto;
use App\Models\Orden;
use App\Models\OTServicio;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdenesIndexExport implements FromCollection, WithHeadings, ShouldAutoSize, WithColumnFormatting, WithStyles, WithEvents
{
    public function __construct(
        protected array $filters,
        protected Authenticatable $user,
    ) {}

    /**
     * Build the base query for Orders using the same filters as before.
     */
    public function query(): Builder
    {
        /** @var User $u */
        $u = $this->user;
        $f = $this->filters;

        $isPrivilegedViewer = $u->hasAnyRole(['admin', 'facturacion', 'gerente_upper']);
        $isTLStrict = $u->hasRole('team_leader') && !$u->hasAnyRole([
            'admin', 'coordinador', 'calidad', 'facturacion', 'gerente_upper', 'Cliente_Supervisor', 'Cliente_Gerente',
        ]);
        $isClienteSupervisor = $u->hasRole('Cliente_Supervisor');
        $isClienteCentro = $u->hasRole('Cliente_Gerente');

        $centrosPermitidos = $this->allowedCentroIds($u);

        if (!$isPrivilegedViewer && !empty($f['centro_costo'])) {
            $cc = CentroCosto::find($f['centro_costo']);
            if (!$cc || !in_array((int) $cc->id_centrotrabajo, array_map('intval', $centrosPermitidos), true)) {
                $f['centro_costo'] = null;
            }
        }

        $q = Orden::query()
            ->with([
                'servicio',
                'centro',
                'teamLeader',
                'items.ajustes',
                'solicitud.centroCosto',
                'solicitud.marca',
                'factura',
                'facturas',
                'area',
                'otServicios.servicio',
                'otServicios.items.ajustes',
            ])
            ->when(!$isPrivilegedViewer, function (Builder $qq) use ($centrosPermitidos) {
                if (!empty($centrosPermitidos)) {
                    $qq->whereIn('id_centrotrabajo', $centrosPermitidos);
                } else {
                    $qq->whereRaw('1=0');
                }
            })
            ->when($isPrivilegedViewer && !empty($f['centro']), fn (Builder $qq) => $qq->where('id_centrotrabajo', $f['centro']))
            ->when(!$isPrivilegedViewer && !empty($f['centro']), function (Builder $qq) use ($f, $centrosPermitidos) {
                if (in_array((int) $f['centro'], array_map('intval', $centrosPermitidos), true)) {
                    $qq->where('id_centrotrabajo', $f['centro']);
                }
            })
            ->when($isTLStrict, fn (Builder $qq) => $qq->where('team_leader_id', $u->id))
            ->when($isClienteSupervisor && !$isClienteCentro, fn (Builder $qq) => $qq->whereHas('solicitud', fn ($w) => $w->where('id_cliente', $u->id)))

            ->when(!empty($f['id']), fn (Builder $qq) => $qq->where('id', $f['id']))
            ->when(!empty($f['estatus']), fn (Builder $qq) => $qq->where('estatus', $f['estatus']))
            ->when(!empty($f['calidad']), fn (Builder $qq) => $qq->where('calidad_resultado', $f['calidad']))
            ->when(!empty($f['servicio']), fn (Builder $qq) => $qq->where('id_servicio', $f['servicio']))
            ->when(!empty($f['centro_costo']), function (Builder $qq) use ($f) {
                $qq->whereHas('solicitud', function (Builder $sq) use ($f) {
                    $sq->where('id_centrocosto', $f['centro_costo']);
                });
            })

            ->when(($f['facturacion'] ?? null) === 'sin_factura', function (Builder $qq) {
                $qq->whereDoesntHave('factura')->whereDoesntHave('facturas');
            })
            ->when(in_array(($f['facturacion'] ?? null), ['facturado', 'por_pagar', 'pagado'], true), function (Builder $qq) use ($f) {
                $qq->where(function (Builder $sub) use ($f) {
                    $sub
                        ->whereHas('facturas', fn ($w) => $w->where('estatus', $f['facturacion']))
                        ->orWhereHas('factura', fn ($w) => $w->where('estatus', $f['facturacion']));
                });
            })

            ->when(!empty($f['desde']) && !empty($f['hasta']), function (Builder $qq) use ($f) {
                $qq->whereBetween('created_at', [
                    Carbon::parse($f['desde'])->startOfDay(),
                    Carbon::parse($f['hasta'])->endOfDay(),
                ]);
            })

            ->when(!empty($f['year']) && !empty($f['week']), function (Builder $qq) use ($f) {
                $qq->whereRaw('YEAR(created_at) = ? AND WEEK(created_at, 1) = ?', [$f['year'], $f['week']]);
            })
            ->when(!empty($f['year']) && empty($f['week']), fn (Builder $qq) => $qq->whereYear('created_at', $f['year']))

            ->orderByDesc('id');

        return $q;
    }

    /**
     * Build a collection of rows where each row is either a (OT x Servicio) or a traditional OT row.
     */
    public function collection(): Collection
    {
        $rows = collect();

        // Iterate orders in chunks to avoid memory spikes
        $this->query()->chunk(500, function ($orders) use ($rows) {
            foreach ($orders as $o) {
                $createdAt = $o->created_at ? Carbon::parse($o->getRawOriginal('created_at') ?? $o->created_at) : null;
                $semana = $createdAt?->isoWeek();
                $fechaOt = $createdAt?->format('d/m/Y');

                // Factura if any
                $factura = null;
                if ($o->relationLoaded('facturas') && $o->facturas && $o->facturas->count() > 0) {
                    $factura = $o->facturas->first();
                } elseif ($o->relationLoaded('factura') && $o->factura) {
                    $factura = $o->factura;
                }

                $fechaFactura = null;
                if ($factura && !empty($factura->fecha_facturado)) {
                    $fechaFactura = Carbon::parse($factura->fecha_facturado)->format('d/m/Y');
                }

                // Si tiene servicios múltiples definidos explícitamente
                if ($o->relationLoaded('otServicios') && $o->otServicios && $o->otServicios->count() > 0) {
                    foreach ($o->otServicios as $s) {
                        $isPending = empty($s->servicio_id) || $s->service_assignment_status === 'pending';
                        $serviceName = $isPending ? 'Pendiente de asignación' : ($s->servicio?->nombre ?? null);
                        // Regla export: cantidad cobrable = solicitado + extras - faltantes (nunca negativa)
                        $cantidadCobrable = $isPending ? 0 : $this->cantidadCobrableServicio($s);
                        // Regla export: costo unitario debe ser el precio real guardado, sin recalcular por total
                        $costoUnitario = $isPending ? 0 : ($s->precio_unitario !== null
                            ? (float) $s->precio_unitario
                            : $this->precioUnitarioDesdeServicioItems($s));
                        // Regla export: total = cantidad cobrable * unitario
                        $costoTotal = $cantidadCobrable * $costoUnitario;

                        $marca = $o->solicitud?->marca?->nombre ?? null;
                        $departamento = trim((string) ($o->solicitud?->centroCosto?->nombre ?? '')) ?: null;
                        $areaSolicita = $o->area?->nombre ?? null;

                        $rows->push([
                            $factura?->folio ?? $factura?->folio_externo ?? null,
                            $semana,
                            $fechaFactura,
                            $o->id,
                            $fechaOt,
                            ($cantidadCobrable > 0 ? $cantidadCobrable : null),
                            $serviceName,
                            $marca,
                            $costoUnitario,
                            $costoTotal,
                            null,
                            $departamento,
                            $areaSolicita,
                        ]);
                    }
                } else {
                    // Tradicional: usar el servicio único en orden->servicio y calcular piezas desde items/solicitud
                    $piezas = $this->cantidadCobrableTradicional($o);

                    $proceso = $o->servicio?->nombre ?? null;
                    $marca = $o->solicitud?->marca?->nombre ?? null;

                    // Regla export: costo unitario real desde detalle de OT, sin recalcular por subtotal/cantidad
                    $costoUnitario = $this->precioUnitarioDesdeOrdenItems($o);
                    $costoTotalSinIva = $piezas * $costoUnitario;

                    $departamento = trim((string) ($o->solicitud?->centroCosto?->nombre ?? '')) ?: null;
                    $areaSolicita = $o->area?->nombre ?? null;

                    $rows->push([
                        $factura?->folio ?? $factura?->folio_externo ?? null,
                        $semana,
                        $fechaFactura,
                        $o->id,
                        $fechaOt,
                        ($piezas > 0 ? $piezas : null),
                        $proceso,
                        $marca,
                        $costoUnitario,
                        $costoTotalSinIva,
                        null,
                        $departamento,
                        $areaSolicita,
                    ]);
                }
            }
        });

        return $rows;
    }

    public function headings(): array
    {
        return [
            'FACTURA',
            'SEMANA',
            'FECHA DE FACTURA',
            'Folio/OT SOLGISTIKA',
            'Fecha',
            'Ctd piezas',
            'Proceso',
            'Marca',
            'Costo unitario (MxN)',
            'Costo total s/iva',
            'OC',
            'DEPARTAMENTO',
            'AREA QUE SOLICITA',
        ];
    }

    public function columnFormats(): array
    {
        return [
            // Costo unitario (MxN)
            'I' => '"$" #,##0.00',
            // Costo total s/iva
            'J' => '"$" #,##0.00',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0B2E5A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $sheet->getStyle('G:G')->getAlignment()->setWrapText(true);
        $sheet->getStyle('L:L')->getAlignment()->setWrapText(true);
        $sheet->getStyle('M:M')->getAlignment()->setWrapText(true);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');

                $highestRow = $sheet->getHighestRow();
                $range = 'A1:M' . $highestRow;

                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(24);
            },
        ];
    }

    private function cantidadCobrableServicio(OTServicio $servicio): int
    {
        $items = $servicio->relationLoaded('items') ? $servicio->items : collect();
        if ($items->count() > 0) {
            return max(0, (int) $items->sum(fn ($item) => (int) ($item->calcularMetricas()['total_cobrable'] ?? 0)));
        }

        return max(0, (int) ($servicio->cantidad ?? 0));
    }

    private function cantidadCobrableTradicional(Orden $orden): int
    {
        $items = $orden->relationLoaded('items') ? $orden->items : collect();
        if ($items->count() > 0) {
            return max(0, (int) $items->sum(fn ($item) => (int) ($item->calcularMetricas()['total_cobrable'] ?? 0)));
        }

        return max(0, (int) ($orden->solicitud?->cantidad ?? 0));
    }

    private function precioUnitarioDesdeServicioItems(OTServicio $servicio): float
    {
        $items = $servicio->relationLoaded('items') ? $servicio->items : collect();
        $itemConPrecio = $items->firstWhere('precio_unitario', '!=', null);

        return $itemConPrecio ? (float) $itemConPrecio->precio_unitario : 0.0;
    }

    private function precioUnitarioDesdeOrdenItems(Orden $orden): float
    {
        $items = $orden->relationLoaded('items') ? $orden->items : collect();
        $itemConPrecio = $items->firstWhere('precio_unitario', '!=', null);

        return $itemConPrecio ? (float) $itemConPrecio->precio_unitario : 0.0;
    }

    private function allowedCentroIds(User $u): array
    {
        if ($u->hasRole('admin')) {
            return [];
        }

        $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn ($v) => (int) $v)->all();
        $primary = (int) ($u->centro_trabajo_id ?? 0);
        if ($primary) {
            $ids[] = $primary;
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
