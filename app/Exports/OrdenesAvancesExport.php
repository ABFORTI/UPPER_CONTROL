<?php

/**
 * OrdenesAvancesExport
 *
 * Excel de corte/parcial de Órdenes de Trabajo.
 * Exporta SOLO los servicios con cantidad avanzada real > 0.
 * Las cantidades reflejan los avances reales registrados en la BD,
 * NO la cantidad planeada original de cada servicio.
 *
 * Si se especifica rango de fechas o periodo (desde/hasta, year/week),
 * el cálculo de avances se limita a ese corte temporal.
 */

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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdenesAvancesExport implements FromCollection, WithHeadings, ShouldAutoSize, WithColumnFormatting, WithStyles, WithEvents
{
    public function __construct(
        protected array $filters,
        protected Authenticatable $user,
    ) {}

    /**
     * Misma consulta base que OrdenesIndexExport, con los mismos filtros de la pantalla.
     */
    public function query(): Builder
    {
        /** @var User $u */
        $u = $this->user;
        $f = $this->filters;

        if (!empty($f['week']) && empty($f['year'])) {
            $f['year'] = (int) now()->year;
        }

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
                // Avances tradicionales con su usuario para comentarios
                'avances.usuario',
                'items.ajustes',
                'solicitud.cliente',
                'solicitud.centroCosto',
                'solicitud.marca',
                'factura',
                'facturas',
                'area',
                'otServicios.servicio',
                // Avances de servicios múltiples con el creador para comentarios
                'otServicios.avances.createdBy',
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
     * Construye las filas del Excel usando cantidades avanzadas reales.
     *
     * Reglas:
     * - Para órdenes multi-servicio (otServicios): una fila por servicio con avance > 0.
     * - Para órdenes tradicionales con items: una fila por item con avance > 0.
     * - Para órdenes tradicionales sin items: una fila por orden con avance total > 0.
     * - Servicios/items/órdenes con cantidad avanzada = 0 se omiten completamente.
     * - Si hay filtro de fecha/periodo, solo se cuentan los avances dentro del corte.
     * - Los importes se recalculan: cantidad_avanzada × precio_unitario.
     */
    public function collection(): Collection
    {
        $rows = collect();

        $this->query()->chunk(500, function ($orders) use ($rows) {
            foreach ($orders as $o) {
                $createdAt = $o->created_at ? Carbon::parse($o->getRawOriginal('created_at') ?? $o->created_at) : null;
                $semana = $createdAt?->isoWeek();
                $fechaOt = $createdAt?->format('d/m/Y');
                $solicitante = $o->solicitud?->cliente?->name ?? null;
                $idSolicitud = $o->solicitud?->id ?? $o->id_solicitud;

                // Factura si existe
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

                // ── Multi-servicio ──────────────────────────────────────────────────────
                if ($o->relationLoaded('otServicios') && $o->otServicios && $o->otServicios->count() > 0) {
                    foreach ($o->otServicios as $s) {
                        $isPending = empty($s->servicio_id) || $s->service_assignment_status === 'pending';
                        if ($isPending) {
                            // Servicios pendientes de asignación no tienen avances reales
                            continue;
                        }

                        // Obtener avances del servicio y aplicar filtro de corte por fecha
                        $avancesServicio = $s->relationLoaded('avances') ? $s->avances : collect();
                        $avancesServicio = $this->filtrarAvancesPorFecha($avancesServicio);

                        // Cantidad avanzada real en el corte seleccionado
                        $cantidadAvanzada = (int) $avancesServicio->sum('cantidad_registrada');

                        // Omitir servicios sin avances en el corte
                        if ($cantidadAvanzada <= 0) {
                            continue;
                        }

                        $serviceName  = $s->servicio?->nombre ?? null;
                        $producto     = $o->descripcion_general ?: ($o->solicitud?->descripcion ?? null);
                        $marca        = $o->solicitud?->marca?->nombre ?? null;
                        $departamento = trim((string) ($o->solicitud?->centroCosto?->nombre ?? '')) ?: null;
                        $areaSolicita = $o->area?->nombre ?? null;
                        $comentariosAvances = $this->construirComentariosAvances($avancesServicio);

                        // Precio unitario del servicio; recalcular importe con cantidad avanzada
                        $costoUnitario = $s->precio_unitario !== null ? (float) $s->precio_unitario : 0.0;
                        $costoTotal    = $cantidadAvanzada * $costoUnitario;

                        $rows->push([
                            $factura?->folio ?? $factura?->folio_externo ?? null,
                            $semana,
                            $fechaFactura,
                            $o->id,
                            $idSolicitud,
                            $fechaOt,
                            $cantidadAvanzada,
                            $serviceName,
                            $producto,
                            null, // Tamaño: avances son a nivel servicio, sin desglose por talla
                            $marca,
                            $costoUnitario,
                            $costoTotal,
                            null,
                            $departamento,
                            $areaSolicita,
                            $solicitante,
                            $comentariosAvances,
                        ]);
                    }

                    continue;
                }

                // ── Tradicional ─────────────────────────────────────────────────────────
                $proceso      = $o->servicio?->nombre ?? null;
                $producto     = $o->descripcion_general ?: ($o->solicitud?->descripcion ?? null);
                $marca        = $o->solicitud?->marca?->nombre ?? null;
                $departamento = trim((string) ($o->solicitud?->centroCosto?->nombre ?? '')) ?: null;
                $areaSolicita = $o->area?->nombre ?? null;

                // Todos los avances de la orden, filtrados por corte si aplica
                $ordenAvancesRaw = $o->relationLoaded('avances') ? $o->avances : collect();
                $ordenAvances    = $this->filtrarAvancesPorFecha($ordenAvancesRaw);

                $ordenItems = $o->relationLoaded('items') ? $o->items : collect();

                if ($ordenItems->count() > 0) {
                    // Si la orden tiene items, exportar una fila por item con avances
                    $hayFilaConAvance = false;

                    foreach ($ordenItems as $item) {
                        // Avances filtrados que correspondan a este item
                        $avancesItem = $ordenAvances->filter(
                            fn ($a) => (int) ($a->id_item ?? 0) === (int) $item->id
                        );
                        $cantidadAvanzada = (int) $avancesItem->sum('cantidad');

                        // Omitir items sin avance en el corte
                        if ($cantidadAvanzada <= 0) {
                            continue;
                        }

                        $hayFilaConAvance  = true;
                        $costoUnitarioItem = $item->precio_unitario !== null
                            ? (float) $item->precio_unitario
                            : $this->precioUnitarioDesdeOrdenItems($o);
                        $costoTotalItem  = $cantidadAvanzada * $costoUnitarioItem;
                        $tamano          = $this->normalizarTamano($item->tamano ?? null);
                        $comentariosItem = $this->construirComentariosAvances($avancesItem);

                        $rows->push([
                            $factura?->folio ?? $factura?->folio_externo ?? null,
                            $semana,
                            $fechaFactura,
                            $o->id,
                            $idSolicitud,
                            $fechaOt,
                            $cantidadAvanzada,
                            $proceso,
                            $producto,
                            $tamano,
                            $marca,
                            $costoUnitarioItem,
                            $costoTotalItem,
                            null,
                            $departamento,
                            $areaSolicita,
                            $solicitante,
                            $comentariosItem,
                        ]);
                    }

                    if ($hayFilaConAvance) {
                        continue;
                    }

                    // Si ningún item tuvo avance, caer al total de la orden
                }

                // Fallback: avance total de la orden sin desglose por item
                $cantidadAvanzada = (int) $ordenAvances->sum('cantidad');

                // Sin avances en el corte → omitir la orden
                if ($cantidadAvanzada <= 0) {
                    continue;
                }

                $costoUnitario      = $this->precioUnitarioDesdeOrdenItems($o);
                $costoTotal         = $cantidadAvanzada * $costoUnitario;
                $comentariosAvances = $this->construirComentariosAvances($ordenAvances);

                $rows->push([
                    $factura?->folio ?? $factura?->folio_externo ?? null,
                    $semana,
                    $fechaFactura,
                    $o->id,
                    $idSolicitud,
                    $fechaOt,
                    $cantidadAvanzada,
                    $proceso,
                    $producto,
                    null,
                    $marca,
                    $costoUnitario,
                    $costoTotal,
                    null,
                    $departamento,
                    $areaSolicita,
                    $solicitante,
                    $comentariosAvances,
                ]);
            }
        });

        return $rows;
    }

    // ── Encabezados: idénticos al Excel completo ────────────────────────────────

    public function headings(): array
    {
        return [
            'FACTURA',
            'SEMANA',
            'FECHA DE FACTURA',
            'Folio/OT SOLGISTIKA',
            'ID Solicitud',
            'Fecha',
            'Ctd piezas',
            'Proceso',
            'PRODUCTO',
            'Tamaño',
            'Marca',
            'Costo unitario (MxN)',
            'Costo total s/iva',
            'OC',
            'DEPARTAMENTO',
            'AREA QUE SOLICITA',
            'SOLICITANTE',
            'Comentarios de avances',
        ];
    }

    // ── Estilos: idénticos al Excel completo ───────────────────────────────────

    public function columnFormats(): array
    {
        return [
            'L' => '"$" #,##0.00',
            'M' => '"$" #,##0.00',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:R1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 10,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0B2E5A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ]);

        $sheet->getStyle('H:H')->getAlignment()->setWrapText(true); // Proceso
        $sheet->getStyle('I:I')->getAlignment()->setWrapText(true); // PRODUCTO
        $sheet->getStyle('J:J')->getAlignment()->setWrapText(true); // Tamaño
        $sheet->getStyle('O:O')->getAlignment()->setWrapText(true); // DEPARTAMENTO
        $sheet->getStyle('P:P')->getAlignment()->setWrapText(true); // AREA QUE SOLICITA
        $sheet->getStyle('Q:Q')->getAlignment()->setWrapText(true); // SOLICITANTE
        $sheet->getStyle('R:R')->getAlignment()->setWrapText(true); // Comentarios

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');

                $highestRow = $sheet->getHighestRow();
                $range      = 'A1:R' . $highestRow;

                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => '000000'],
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

    // ── Helpers privados ───────────────────────────────────────────────────────

    /**
     * Filtra una colección de avances por el rango de fechas / periodo de los filtros.
     * Si no hay filtro de fechas, devuelve todos los avances (acumulado completo).
     *
     * Funciona tanto con OTServicioAvance (campo created_at) como con Avance tradicional.
     */
    private function filtrarAvancesPorFecha(Collection $avances): Collection
    {
        $f = $this->filters;

        if (!empty($f['desde']) && !empty($f['hasta'])) {
            $desde = Carbon::parse($f['desde'])->startOfDay();
            $hasta = Carbon::parse($f['hasta'])->endOfDay();

            return $avances->filter(function ($a) use ($desde, $hasta) {
                if (!$a->created_at) {
                    return false;
                }
                $dt = $a->created_at instanceof Carbon
                    ? $a->created_at
                    : Carbon::parse($a->created_at);

                return $dt->between($desde, $hasta);
            });
        }

        if (!empty($f['year']) && !empty($f['week'])) {
            $anio  = (int) $f['year'];
            $semana = (int) $f['week'];

            return $avances->filter(function ($a) use ($anio, $semana) {
                if (!$a->created_at) {
                    return false;
                }
                $dt = $a->created_at instanceof Carbon
                    ? $a->created_at
                    : Carbon::parse($a->created_at);

                return (int) $dt->year === $anio && (int) $dt->isoWeek() === $semana;
            });
        }

        if (!empty($f['year'])) {
            $anio = (int) $f['year'];

            return $avances->filter(function ($a) use ($anio) {
                if (!$a->created_at) {
                    return false;
                }
                $dt = $a->created_at instanceof Carbon
                    ? $a->created_at
                    : Carbon::parse($a->created_at);

                return (int) $dt->year === $anio;
            });
        }

        // Sin filtro de fecha: se usan todos los avances acumulados
        return $avances;
    }

    private function normalizarTamano(?string $tamano): ?string
    {
        $valor = trim((string) $tamano);
        if ($valor === '') {
            return null;
        }

        $genericos = ['item', 'servicio adicional', 'sku'];
        if (in_array(mb_strtolower($valor), $genericos, true)) {
            return null;
        }

        return $valor;
    }

    private function precioUnitarioDesdeOrdenItems(Orden $orden): float
    {
        $items        = $orden->relationLoaded('items') ? $orden->items : collect();
        $itemConPrecio = $items->firstWhere('precio_unitario', '!=', null);

        return $itemConPrecio ? (float) $itemConPrecio->precio_unitario : 0.0;
    }

    /**
     * Construye la columna de comentarios a partir de los avances (OTServicioAvance o Avance).
     * Reutiliza la misma lógica que el Excel completo.
     */
    private function construirComentariosAvances(Collection $avances): ?string
    {
        if ($avances->isEmpty()) {
            return null;
        }

        $lineas = $avances
            ->filter(fn ($avance) => trim((string) ($avance->comentario ?? '')) !== '')
            ->sortBy('created_at')
            ->map(function ($avance) {
                $comentario = trim((string) ($avance->comentario ?? ''));
                $usuario    = $avance->createdBy?->name
                    ?? $avance->usuario?->name
                    ?? 'Sistema';

                $fecha    = $this->formatearFechaComentario($avance->created_at ?? null);
                $contexto = $fecha
                    ? "[{$fecha} - {$usuario}]"
                    : "[{$usuario}]";

                return $contexto . ' ' . $comentario;
            })
            ->values();

        if ($lineas->isEmpty()) {
            return null;
        }

        return $lineas->implode("\n");
    }

    private function formatearFechaComentario(mixed $fecha): ?string
    {
        if (!$fecha) {
            return null;
        }

        try {
            $dt    = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);
            $texto = $dt->format('d/m/Y h:i A');

            return str_replace(['AM', 'PM'], ['a. m.', 'p. m.'], $texto);
        } catch (\Throwable) {
            return null;
        }
    }

    private function allowedCentroIds(User $u): array
    {
        if ($u->hasRole('admin')) {
            return [];
        }

        $ids     = $u->centros()->pluck('centros_trabajo.id')->map(fn ($v) => (int) $v)->all();
        $primary = (int) ($u->centro_trabajo_id ?? 0);
        if ($primary) {
            $ids[] = $primary;
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
