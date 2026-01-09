<?php

namespace App\Exports;

use App\Models\CentroCosto;
use App\Models\OrdenItem;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrdenesFacturacionExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithChunkReading
{
    public function __construct(
        protected array $filters,
        protected Authenticatable $user,
    ) {}

    public function query(): Builder
    {
        /** @var User $u */
        $u = $this->user;
        $f = $this->filters;

        $isPrivilegedViewer = $u->hasAnyRole(['admin', 'facturacion', 'gerente_upper']);
        $isTL = $u->hasRole('team_leader');
        $isClienteSupervisor = $u->hasRole('Cliente_Supervisor');
        $isClienteCentro = $u->hasRole('Cliente_Gerente');

        $centrosPermitidos = $this->allowedCentroIds($u);

        if (!$isPrivilegedViewer && !empty($f['centro_costo'])) {
            $cc = CentroCosto::find($f['centro_costo']);
            if (!$cc || !in_array((int) $cc->id_centrotrabajo, array_map('intval', $centrosPermitidos), true)) {
                $f['centro_costo'] = null;
            }
        }

        $q = OrdenItem::query()
            ->with([
                'orden.servicio',
                'orden.centro',
                'orden.solicitud.centroCosto',
            ])
            ->whereHas('orden', function (Builder $qq) use ($u, $f, $isPrivilegedViewer, $centrosPermitidos, $isTL, $isClienteSupervisor, $isClienteCentro) {
                $qq
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
                    ->when($isTL, fn (Builder $sub) => $sub->where('team_leader_id', $u->id))
                    ->when($isClienteSupervisor && !$isClienteCentro, fn (Builder $sub) => $sub->whereHas('solicitud', fn ($w) => $w->where('id_cliente', $u->id)))

                    ->when(!empty($f['id']), fn (Builder $sub) => $sub->where('id', $f['id']))
                    ->when(!empty($f['estatus']), fn (Builder $sub) => $sub->where('estatus', $f['estatus']))
                    ->when(!empty($f['calidad']), fn (Builder $sub) => $sub->where('calidad_resultado', $f['calidad']))
                    ->when(!empty($f['servicio']), fn (Builder $sub) => $sub->where('id_servicio', $f['servicio']))
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
                    ->when(!empty($f['year']) && empty($f['week']), fn (Builder $sub) => $sub->whereYear('created_at', $f['year']));
            })
            ->orderByDesc('id');

        return $q;
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

    public function map($item): array
    {
        $orden = $item->orden;

        $fechaElaboracion = $orden?->solicitud?->created_at
            ? Carbon::parse($orden->solicitud->getRawOriginal('created_at') ?? $orden->solicitud->created_at)
            : null;

        $periodoWeek = (int) ($this->filters['week'] ?? 0);
        $periodo = $periodoWeek > 0
            ? ('S' . $periodoWeek)
            : ($fechaElaboracion ? ('S' . $fechaElaboracion->isoWeek()) : null);

        $cantidadPlan = (int) ($item->cantidad_planeada ?? 0);
        $cantidadReal = (int) ($item->cantidad_real ?? 0);
        $cantidad = $cantidadPlan > 0 ? $cantidadPlan : ($cantidadReal > 0 ? $cantidadReal : (int) ($orden?->solicitud?->cantidad ?? 0));
        if ($cantidad <= 0) {
            $cantidad = null;
        }

        $precio = $item->precio_unitario;
        if ($precio === null && $cantidad && (float) ($item->subtotal ?? 0) > 0) {
            $precio = (float) $item->subtotal / (int) $cantidad;
        }

        $fechaEntrega = null;
        if (!empty($orden?->fecha_completada)) {
            $fechaEntrega = Carbon::parse($orden->fecha_completada);
        }

        $centro = $orden?->centro?->prefijo ?: ($orden?->centro?->nombre ?? null);

        $datosOt = $orden?->servicio?->nombre;
        if ($datosOt && $orden?->id) {
            $datosOt = $datosOt . ' OT: ' . $orden->id;
        }

        return [
            $orden?->centro?->numero_centro ?? null,
            $fechaElaboracion?->format('d/m/Y'),
            $periodo,
            $centro,
            $orden?->solicitud?->centroCosto?->nombre ?? null,
            $cantidad,
            $precio !== null ? (float) $precio : null,
            $datosOt,
            $fechaEntrega?->format('d/m/Y'),
        ];
    }

    public function chunkSize(): int
    {
        return 500;
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
