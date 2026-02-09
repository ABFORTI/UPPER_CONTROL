<?php

namespace App\Exports;

use App\Models\CentroCosto;
use App\Models\Orden;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrdenesFacturacionExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'solicitud.centroCosto',
                'solicitud.cliente',
                'otServicios.servicio',
                'items:id,id_orden,cantidad_planeada,cantidad_real,precio_unitario,subtotal',
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
                        $cantidad = (int) ($otServicio->cantidad ?? 0);
                        if ($cantidad <= 0) $cantidad = null;

                        $precio = $otServicio->precio_unitario;
                        $precio = $precio !== null ? (float) $precio : 0.0;

                        $nombreServicio = $otServicio?->servicio?->nombre ?? null;
                        $datosOt = $nombreServicio ? ($nombreServicio . ' OT: ' . $orden->id) : ('OT: ' . $orden->id);

                        $rows->push([
                            $cliente,
                            $fechaElaboracion?->format('d/m/Y'),
                            $periodo,
                            $centro,
                            $centroCostos,
                            $cantidad,
                            $precio,
                            $datosOt,
                            $fechaEntrega?->format('d/m/Y'),
                        ]);
                    }

                    continue;
                }

                // Tradicional: 1 fila (compat)
                $items = $orden->relationLoaded('items') ? $orden->items : collect();
                $cantidadPlaneada = (int) $items->sum('cantidad_planeada');
                $cantidadReal = (int) $items->sum('cantidad_real');
                $cantidad = $cantidadPlaneada > 0
                    ? $cantidadPlaneada
                    : ($cantidadReal > 0 ? $cantidadReal : (int) ($orden?->solicitud?->cantidad ?? 0));
                if ($cantidad <= 0) $cantidad = null;

                $precio = null;
                $firstPrecio = $items->firstWhere('precio_unitario', '!=', null);
                if ($firstPrecio) {
                    $precio = (float) $firstPrecio->precio_unitario;
                } else {
                    $subtotal = (float) $items->sum('subtotal');
                    if ($cantidad && $subtotal > 0) {
                        $precio = $subtotal / (int) $cantidad;
                    }
                }
                if ($precio === null) $precio = 0.0;

                $nombreServicio = $orden?->servicio?->nombre ?? null;
                $datosOt = $nombreServicio ? ($nombreServicio . ' OT: ' . $orden->id) : ('OT: ' . $orden->id);

                $rows->push([
                    $cliente,
                    $fechaElaboracion?->format('d/m/Y'),
                    $periodo,
                    $centro,
                    $centroCostos,
                    $cantidad,
                    (float) $precio,
                    $datosOt,
                    $fechaEntrega?->format('d/m/Y'),
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
            'Cantidad',
            'Precio',
            'DATOS DE LA ORDEN DE TRABAJO',
            'Fecha de entrega',
        ];
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
