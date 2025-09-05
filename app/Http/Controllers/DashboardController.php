<?php
// app/Http/Controllers/DashboardController.php
namespace App\Http\Controllers;

use App\Models\Solicitud;
use App\Models\Orden;
use App\Models\Factura;
use App\Models\ServicioEmpresa;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $req)
    {
        $u = $req->user();

        // Rango por defecto: últimos 30 días
        $desde = $req->date('desde') ?: now()->subDays(30)->startOfDay();
        $hasta = $req->date('hasta') ?: now()->endOfDay();

        // Centro: admins/facturación pueden elegir, el resto se fija al suyo
        $centroId = $u->hasAnyRole(['admin','facturacion'])
            ? ($req->integer('centro') ?: null)
            : (int) $u->centro_trabajo_id;

        // --- KPIs básicos
        $solicitudesTotal = Solicitud::when($centroId, fn($q)=>$q->where('id_centrotrabajo',$centroId))
            ->whereBetween('created_at', [$desde, $hasta])->count();

        $otsQuery = Orden::when($centroId, fn($q)=>$q->where('id_centrotrabajo',$centroId))
            ->whereBetween('created_at', [$desde, $hasta]);

        $otsTotal        = (clone $otsQuery)->count();
        $otsCompletadas  = (clone $otsQuery)->where('estatus','completada')->count();
        $otsCalPendiente = (clone $otsQuery)->where('estatus','completada')->where('calidad_resultado','pendiente')->count();

        $factQuery = Factura::whereBetween('created_at', [$desde, $hasta])
            ->when($centroId, fn($q)=>$q->whereHas('orden', fn($w)=>$w->where('id_centrotrabajo',$centroId)));

        $factPendientes = (clone $factQuery)->where('estatus','pendiente')->count();
        $montoFacturado = (clone $factQuery)->whereIn('estatus', ['facturado','cobrado','pagado'])->sum('total');

        // --- Serie: OTs por día por estatus (tabla simple)
        $porDia = (clone $otsQuery)
            ->selectRaw('DATE(created_at) as d, estatus, COUNT(*) c')
            ->groupBy('d','estatus')
            ->orderBy('d')
            ->get()
            ->groupBy('d')
            ->map(function($rows,$d){
                $row = ['fecha'=>$d,'generada'=>0,'asignada'=>0,'en_proceso'=>0,'completada'=>0,'autorizada_cliente'=>0];
                foreach ($rows as $r) { $row[$r->estatus] = (int)$r->c; }
                $row['total'] = array_sum(array_intersect_key($row, array_flip(['generada','asignada','en_proceso','completada','autorizada_cliente'])));
                return $row;
            })->values();

        // --- Serie: Facturación por mes (suma)
        $porMes = (clone $factQuery)
            ->whereIn('estatus',['facturado','cobrado','pagado'])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(total) as t")
            ->groupBy('ym')->orderBy('ym')->get();

        // --- Top servicios por OTs completadas
        $topServicios = (clone $otsQuery)
            ->where('estatus','completada')
            ->selectRaw('id_servicio, COUNT(*) as c')
            ->groupBy('id_servicio')->orderByDesc('c')->limit(10)->get()
            ->map(function($r){
                $nombre = ServicioEmpresa::find($r->id_servicio)?->nombre ?? '—';
                return ['servicio'=>$nombre, 'completadas'=>$r->c];
            });

        // Centros para filtro (solo admins/facturación)
        $centros = $u->hasAnyRole(['admin','facturacion'])
            ? DB::table('centros_trabajo')->select('id','nombre')->orderBy('nombre')->get()
            : collect([]);

        return Inertia::render('Dashboard/Index', [
            'kpis' => [
                'solicitudes' => $solicitudesTotal,
                'ots'         => $otsTotal,
                'ots_completadas' => $otsCompletadas,
                'ots_cal_pend'    => $otsCalPendiente,
                'fact_pendientes' => $factPendientes,
                'monto_facturado' => (float)$montoFacturado,
            ],
            'series' => [
                'ots_por_dia'   => $porDia,
                'fact_por_mes'  => $porMes,
                'top_servicios' => $topServicios,
            ],
            'filters' => [
                'desde'  => $desde->toDateString(),
                'hasta'  => $hasta->toDateString(),
                'centro' => $centroId,
            ],
            'centros' => $centros,
            'urls' => [
                'index'           => route('dashboard'), // 👈 IMPORTANTE
                'export_ots'      => route('dashboard.export.ots',      ['desde'=>$desde->toDateString(),'hasta'=>$hasta->toDateString(),'centro'=>$centroId]),
                'export_facturas' => route('dashboard.export.facturas', ['desde'=>$desde->toDateString(),'hasta'=>$hasta->toDateString(),'centro'=>$centroId]),
            ]
        ]);
    }

    // ----- Exports CSV -----
    public function exportOts(Request $req): StreamedResponse
    {
        $desde = $req->date('desde') ?: now()->subDays(30)->startOfDay();
        $hasta = $req->date('hasta') ?: now()->endOfDay();
        $centroId = $req->integer('centro');

        $u = $req->user();
        if (!$u->hasAnyRole(['admin','facturacion'])) {
            $centroId = (int)$u->centro_trabajo_id;
        }

        $rows = Orden::with(['servicio:id,nombre','centro:id,nombre'])
            ->when($centroId, fn($q)=>$q->where('id_centrotrabajo',$centroId))
            ->whereBetween('created_at', [$desde, $hasta])
            ->orderBy('id')
            ->get(['id','folio','id_servicio','id_centrotrabajo','estatus','calidad_resultado','created_at'])
            ->map(function($o){
                return [
                    $o->id,
                    $o->folio,
                    $o->servicio?->nombre,
                    $o->centro?->nombre,
                    $o->estatus,
                    $o->calidad_resultado,
                    $o->created_at,
                ];
            });

        $headers = ['ID','Folio','Servicio','Centro','Estatus','Calidad','Creado'];
        return $this->streamCsv('ots.csv', $headers, $rows);
    }

    public function exportFacturas(Request $req): StreamedResponse
    {
        $desde = $req->date('desde') ?: now()->subDays(30)->startOfDay();
        $hasta = $req->date('hasta') ?: now()->endOfDay();
        $centroId = $req->integer('centro');

        $u = $req->user();
        if (!$u->hasAnyRole(['admin','facturacion'])) {
            $centroId = (int)$u->centro_trabajo_id;
        }

        $rows = Factura::with(['orden.servicio:id,nombre','orden.centro:id,nombre'])
            ->when($centroId, fn($q)=>$q->whereHas('orden', fn($w)=>$w->where('id_centrotrabajo',$centroId)))
            ->whereBetween('created_at', [$desde, $hasta])
            ->orderBy('id')
            ->get(['id','id_orden','total','folio','estatus','created_at'])
            ->map(function($f){
                return [
                    $f->id,
                    $f->id_orden,
                    $f->orden?->servicio?->nombre,
                    $f->orden?->centro?->nombre,
                    number_format((float)$f->total,2,'.',''),
                    $f->folio,
                    $f->estatus,
                    $f->created_at,
                ];
            });

        $headers = ['ID','OT','Servicio','Centro','Total','Folio','Estatus','Creado'];
        return $this->streamCsv('facturas.csv', $headers, $rows);
    }

    private function streamCsv(string $filename, array $headers, $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers,$rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $r) fputcsv($out, $r);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
