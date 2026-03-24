<?php

namespace App\Exports;

use App\Models\CentroCosto;
use App\Models\Orden;
use App\Models\OTServicio;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class OrdenesFacturacionExport implements FromCollection, WithHeadings, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    public function __construct(
        protected array $filters,
        protected Authenticatable $user,
    ) {}

    public function collection(): Collection
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
                'avances.usuario',
                'solicitud.centroCosto',
                'solicitud.cliente',
                'otServicios.servicio',
                'otServicios.avances.createdBy',
                'otServicios.items.ajustes',
                'items.ajustes',
            ])
            ->when(!$isPrivilegedViewer, function (Builder $sub) use ($centrosPermitidos) {
                if (!empty($centrosPermitidos)) {
                    $sub->whereIn('id_centrotrabajo', $centrosPermitidos);
                } else {
                    $sub->whereRaw('1=0');
                }
            })
            ->when($isPrivilegedViewer && !empty($f['centro']), fn (Builder $sub) => $sub->where('id_centrotrabajo', $f['centro']))
            ->when(!$isPrivilegedViewer && !empty($f['centro']), function (Builder $sub) use ($f, $centrosPermitidos) {
                if (in_array((int) $f['centro'], array_map('intval', $centrosPermitidos), true)) {
                    $sub->where('id_centrotrabajo', $f['centro']);
                }
            })
            ->when($isTLStrict, fn (Builder $sub) => $sub->where('team_leader_id', $u->id))
            ->when($isClienteSupervisor && !$isClienteCentro, fn (Builder $sub) => $sub->whereHas('solicitud', fn ($w) => $w->where('id_cliente', $u->id)))

            ->when(!empty($f['id']), fn (Builder $sub) => $sub->where('id', $f['id']))
            ->when(!empty($f['estatus']), fn (Builder $sub) => $sub->where('estatus', $f['estatus']))
            ->when(!empty($f['calidad']), fn (Builder $sub) => $sub->where('calidad_resultado', $f['calidad']))
            ->when(!empty($f['servicio']), function (Builder $sub) use ($f) {
                $svcId = $f['servicio'];
                $sub->where(function (Builder $w) use ($svcId) {
                    $w
                        ->where('id_servicio', $svcId)
                        ->orWhereHas('otServicios', fn (Builder $s) => $s->where('servicio_id', $svcId));
                });
            })
            ->when(!empty($f['centro_costo']), function (Builder $sub) use ($f) {
                $sub->whereHas('solicitud', function (Builder $sq) use ($f) {
                    $sq->where('id_centrocosto', $f['centro_costo']);
                });
            })

            ->when(($f['facturacion'] ?? null) === 'sin_factura', function (Builder $sub) {
                $sub->whereDoesntHave('factura')->whereDoesntHave('facturas');
            })
            ->when(in_array(($f['facturacion'] ?? null), ['facturado', 'por_pagar', 'pagado'], true), function (Builder $sub) use ($f) {
                $sub->where(function (Builder $w) use ($f) {
                    $w
                        ->whereHas('facturas', fn ($ff) => $ff->where('estatus', $f['facturacion']))
                        ->orWhereHas('factura', fn ($ff) => $ff->where('estatus', $f['facturacion']));
                });
            })

            ->when(!empty($f['desde']) && !empty($f['hasta']), function (Builder $sub) use ($f) {
                $sub->whereBetween('created_at', [
                    Carbon::parse($f['desde'])->startOfDay(),
                    Carbon::parse($f['hasta'])->endOfDay(),
                ]);
            })

            ->when(!empty($f['year']) && !empty($f['week']), function (Builder $sub) use ($f) {
                $sub->whereRaw('YEAR(created_at) = ? AND WEEK(created_at, 1) = ?', [$f['year'], $f['week']]);
            })
            ->when(!empty($f['year']) && empty($f['week']), fn (Builder $sub) => $sub->whereYear('created_at', $f['year']))
            ->orderByDesc('id');

        $rows = collect();

        $q->chunk(500, function ($chunk) use (&$rows, $f) {
            foreach ($chunk as $orden) {
                $fechaElaboracion = $orden?->solicitud?->created_at
                    ? Carbon::parse($orden->solicitud->getRawOriginal('created_at') ?? $orden->solicitud->created_at)
                    : null;
                $idSolicitud = $orden?->solicitud?->id ?? $orden?->id_solicitud;

                $periodoWeek = (int) ($f['week'] ?? 0);
                $periodo = $periodoWeek > 0
                    ? ('S' . $periodoWeek)
                    : ($fechaElaboracion ? ('S' . $fechaElaboracion->isoWeek()) : null);

                $fechaEntrega = null;
                if (!empty($orden?->fecha_completada)) {
                    $fechaEntrega = Carbon::parse($orden->fecha_completada);
                }

                $centro = $orden?->centro?->prefijo ?: ($orden?->centro?->nombre ?? null);
                $centroCostos = $orden?->solicitud?->centroCosto?->nombre ?? null;
                $cliente = $orden?->solicitud?->cliente?->name ?? null;

                $otServicios = $orden->relationLoaded('otServicios') ? $orden->otServicios : collect();

                // Multi-servicio: N filas, una por cada servicio en ot_servicios
                if ($otServicios->count() > 0) {
                    $svcFilter = $f['servicio'] ?? null;
                    $serviciosFiltrados = $svcFilter
                        ? $otServicios->where('servicio_id', (int) $svcFilter)
                        : $otServicios;

                    foreach ($serviciosFiltrados as $otServicio) {
                        $isPending = empty($otServicio->servicio_id) || $otServicio->service_assignment_status === 'pending';
                        $nombreServicio = $isPending ? 'Pendiente de asignación' : ($otServicio?->servicio?->nombre ?? null);
                        $itemsServicio = $otServicio->relationLoaded('items') ? $otServicio->items : collect();
                        $comentariosAvances = $this->construirComentariosAvances(
                            $otServicio->relationLoaded('avances') ? $otServicio->avances : collect()
                        );

                        // Si hay desglose por items/tamanos, exportar una fila por item con su propio precio.
                        if (!$isPending && $itemsServicio->count() > 0) {
                            foreach ($itemsServicio as $item) {
                                $cantidadCobrableItem = $this->cantidadCobrableItem($item);
                                $cantidad = $cantidadCobrableItem > 0 ? $cantidadCobrableItem : null;
                                $precio = $item->precio_unitario !== null
                                    ? (float) $item->precio_unitario
                                    : ($otServicio->precio_unitario !== null ? (float) $otServicio->precio_unitario : 0.0);

                                $tamano = $this->normalizarTamano($item->tamano ?? null);
                                $datosOtBase = $nombreServicio ?: 'OT';
                                $datosOt = $tamano
                                    ? ($datosOtBase . ' - ' . $tamano . ' OT: ' . $orden->id)
                                    : ($datosOtBase . ' OT: ' . $orden->id);

                                $rows->push([
                                    $cliente,
                                    $fechaElaboracion?->format('d/m/Y'),
                                    $periodo,
                                    $centro,
                                    $centroCostos,
                                    $idSolicitud,
                                    $cantidad,
                                    $precio,
                                    $datosOt,
                                    $fechaEntrega?->format('d/m/Y'),
                                    $comentariosAvances,
                                ]);
                            }

                            continue;
                        }

                        // Fallback legacy: fila por servicio cuando no hay items.
                        $cantidadCobrable = $isPending ? 0 : $this->cantidadCobrableServicio($otServicio);
                        $cantidad = $cantidadCobrable > 0 ? $cantidadCobrable : null;
                        $precio = $isPending ? 0 : ($otServicio->precio_unitario !== null
                            ? (float) $otServicio->precio_unitario
                            : $this->precioUnitarioDesdeServicioItems($otServicio));
                        $datosOt = $nombreServicio ? ($nombreServicio . ' OT: ' . $orden->id) : ('OT: ' . $orden->id);

                        $rows->push([
                            $cliente,
                            $fechaElaboracion?->format('d/m/Y'),
                            $periodo,
                            $centro,
                            $centroCostos,
                            $idSolicitud,
                            $cantidad,
                            $precio,
                            $datosOt,
                            $fechaEntrega?->format('d/m/Y'),
                            $comentariosAvances,
                        ]);
                    }

                    continue;
                }

                // Tradicional: si hay items, exportar una fila por item/tamano.
                $nombreServicio = $orden?->servicio?->nombre ?? null;
                $comentariosAvances = $this->construirComentariosAvances(
                    $orden->relationLoaded('avances') ? $orden->avances : collect()
                );
                $ordenItems = $orden->relationLoaded('items') ? $orden->items : collect();

                if ($ordenItems->count() > 0) {
                    foreach ($ordenItems as $item) {
                        $cantidadCobrableItem = $this->cantidadCobrableItem($item);
                        $cantidad = $cantidadCobrableItem > 0 ? $cantidadCobrableItem : null;
                        $precio = $item->precio_unitario !== null
                            ? (float) $item->precio_unitario
                            : $this->precioUnitarioDesdeOrdenItems($orden);

                        $tamano = $this->normalizarTamano($item->tamano ?? null);
                        $datosOtBase = $nombreServicio ?: 'OT';
                        $datosOt = $tamano
                            ? ($datosOtBase . ' - ' . $tamano . ' OT: ' . $orden->id)
                            : ($datosOtBase . ' OT: ' . $orden->id);

                        $rows->push([
                            $cliente,
                            $fechaElaboracion?->format('d/m/Y'),
                            $periodo,
                            $centro,
                            $centroCostos,
                            $idSolicitud,
                            $cantidad,
                            (float) $precio,
                            $datosOt,
                            $fechaEntrega?->format('d/m/Y'),
                            $comentariosAvances,
                        ]);
                    }

                    continue;
                }

                // Fallback legacy tradicional sin items.
                $cantidadCobrable = $this->cantidadCobrableTradicional($orden);
                $cantidad = $cantidadCobrable > 0 ? $cantidadCobrable : null;
                $precio = $this->precioUnitarioDesdeOrdenItems($orden);
                $datosOt = $nombreServicio ? ($nombreServicio . ' OT: ' . $orden->id) : ('OT: ' . $orden->id);

                $rows->push([
                    $cliente,
                    $fechaElaboracion?->format('d/m/Y'),
                    $periodo,
                    $centro,
                    $centroCostos,
                    $idSolicitud,
                    $cantidad,
                    (float) $precio,
                    $datosOt,
                    $fechaEntrega?->format('d/m/Y'),
                    $comentariosAvances,
                ]);
            }
        });

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Cliente',
            'Fecha de elaboración',
            'Periodo',
            'Centro',
            'Centro de costos',
            'ID Solicitud',
            'Cantidad',
            'Precio',
            'DATOS DE LA ORDEN DE TRABAJO',
            'Fecha de entrega',
            'Comentarios de avances',
        ];
    }

    public function columnFormats(): array
    {
        return [
            // Precio
            'H' => '"$" #,##0.00',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('K:K')->getAlignment()->setWrapText(true);
                $sheet->getStyle('K:K')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            },
        ];
    }

    private function construirComentariosAvances(Collection $avances): ?string
    {
        if ($avances->isEmpty()) {
            return null;
        }

        $lineas = $avances
            ->filter(function ($avance) {
                return trim((string) ($avance->comentario ?? '')) !== '';
            })
            ->sortBy('created_at')
            ->map(function ($avance) {
                $comentario = trim((string) ($avance->comentario ?? ''));
                $usuario = $avance->createdBy?->name
                    ?? $avance->usuario?->name
                    ?? 'Sistema';

                $fecha = $this->formatearFechaComentario($avance->created_at ?? null);
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
            $dt = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);
            $texto = $dt->format('d/m/Y h:i A');

            return str_replace(['AM', 'PM'], ['a. m.', 'p. m.'], $texto);
        } catch (\Throwable $e) {
            return null;
        }
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

        return max(0, (int) ($orden?->solicitud?->cantidad ?? 0));
    }

    private function cantidadCobrableItem(object $item): int
    {
        if (method_exists($item, 'calcularMetricas')) {
            return max(0, (int) ($item->calcularMetricas()['total_cobrable'] ?? 0));
        }

        return 0;
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
