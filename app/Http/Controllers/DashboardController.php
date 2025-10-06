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
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OtExport;
use App\Exports\FacturaExport;

class DashboardController extends Controller
{
    public function index(Request $req)
    {
        $u = $req->user();
    $isCliente = method_exists($u, 'hasRole') ? $u->hasRole('cliente') : false;
    $isClienteCentro = method_exists($u, 'hasRole') ? $u->hasRole('cliente_centro') : false;

        // Rango por defecto: últimos 30 días
        // Periodo por semana ISO: week del año (no editable como fechas)
        $year = $req->integer('year') ?: now()->year;
        $week = $req->integer('week') ?: now()->isoWeek;
        $base = CarbonImmutable::now()->setISODate($year, $week);
        $desde = $base->startOfWeek();
        $hasta = $base->endOfWeek();

        // Centro: admins/facturación pueden elegir, el resto se fija al suyo
        $centroId = $u->hasAnyRole(['admin','facturacion'])
            ? ($req->integer('centro') ?: null)
            : (int) $u->centro_trabajo_id;

        // --- KPIs básicos
        $solicitudesTotal = Solicitud::when($centroId, fn($q)=>$q->where('id_centrotrabajo',$centroId))
            ->when($isCliente && !$isClienteCentro, fn($q)=>$q->where('id_cliente', $u->id))
            ->whereBetween('created_at', [$desde, $hasta])->count();

        $otsQuery = Orden::when($centroId, fn($q)=>$q->where('id_centrotrabajo',$centroId))
            ->when($isCliente && !$isClienteCentro, fn($q)=>$q->whereHas('solicitud', fn($w)=>$w->where('id_cliente',$u->id)))
            ->whereBetween('created_at', [$desde, $hasta]);

        $otsTotal        = (clone $otsQuery)->count();
        $otsCompletadas  = (clone $otsQuery)->where('estatus','completada')->count();
        $otsCalPendiente = (clone $otsQuery)->where('estatus','completada')->where('calidad_resultado','pendiente')->count();
        $otsAutCliente   = (clone $otsQuery)->where('estatus','autorizada_cliente')->count();

        // Distribución por estatus (todas las principales)
        $estatusDistrib = (clone $otsQuery)
            ->selectRaw('estatus, COUNT(*) c')
            ->groupBy('estatus')->pluck('c','estatus');
        $estatusMap = ['generada'=>0,'asignada'=>0,'en_proceso'=>0,'completada'=>0,'autorizada_cliente'=>0];
        foreach ($estatusDistrib as $k=>$v) { if(array_key_exists($k,$estatusMap)) $estatusMap[$k]=(int)$v; }

        // Calidad breakdown (solo OTs completadas en rango / centro)
        $calidadDistrib = (clone $otsQuery)
            ->where('estatus','completada')
            ->selectRaw('calidad_resultado, COUNT(*) c')
            ->groupBy('calidad_resultado')->pluck('c','calidad_resultado');
        $calidadMap = ['pendiente'=>0,'validado'=>0,'rechazado'=>0];
        foreach ($calidadDistrib as $k=>$v) { if(array_key_exists($k,$calidadMap)) $calidadMap[$k]=(int)$v; }
        $calidadTotalEvaluables = array_sum($calidadMap);
        $tasaValidacion = $calidadTotalEvaluables > 0 ? round(($calidadMap['validado'] / $calidadTotalEvaluables) * 100,1) : 0.0;

        // Quitar cálculos y series relacionados con dinero/facturación del dashboard
        $factPendientes = 0;
        $montoFacturado = 0.0;
        $factMap = ['pendiente'=>0,'facturado'=>0,'cobrado'=>0,'pagado'=>0];
        $ingresosDiarios = collect();

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
        $porMes = collect();

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

        // Usuarios del centro con roles (si hay centro seleccionado o asignado)
        $usuariosCentro = collect();
        if ($centroId) {
            $usuariosCentro = \App\Models\User::with(['roles:id,name','centros:id'])
                ->select('users.id','users.name','users.email','users.centro_trabajo_id')
                ->where(function($q) use ($centroId){
                    $q->where('users.centro_trabajo_id', $centroId)
                      ->orWhereHas('centros', fn($w)=>$w->where('centros_trabajo.id',$centroId));
                })
                ->get()
                ->unique('id')
                ->values()
                ->map(fn($u)=>[
                    'id'    => $u->id,
                    'nombre'=> $u->name,
                    'email' => $u->email,
                    'roles' => $u->roles->pluck('name')->values(),
                ]);
        }

        return Inertia::render('Dashboard/Index', [
            'kpis' => [
                'solicitudes' => $solicitudesTotal,
                'ots'         => $otsTotal,
                'ots_completadas' => $otsCompletadas,
                'ots_cal_pend'    => $otsCalPendiente,
                'ots_aut_cliente' => $otsAutCliente,
                // quitar: 'fact_pendientes', 'monto_facturado'
                'tasa_validacion' => $tasaValidacion,
            ],
            'series' => [
                'ots_por_dia'   => $porDia,
                'top_servicios' => $topServicios,
                // quitar: 'fact_por_mes', 'ingresos_diarios'
            ],
            'distribuciones' => [
                'estatus_ots' => $estatusMap,
                'calidad'     => $calidadMap,
                // quitar: 'facturas'
            ],
            'filters' => [
                'year'   => $year,
                'week'   => $week,
                'desde'  => $desde->toDateString(),
                'hasta'  => $hasta->toDateString(),
                'centro' => $centroId,
            ],
            'centros' => $centros,
            'usuarios_centro' => $usuariosCentro,
            'urls' => [
                'index'           => route('dashboard'), // 👈 IMPORTANTE
                'export_ots'      => route('dashboard.export.ots',      ['desde'=>$desde->toDateString(),'hasta'=>$hasta->toDateString(),'centro'=>$centroId]),
                // quitar: export_facturas
            ]
        ]);
    }

    // ----- Exports CSV -----
    public function exportOts(Request $req)
    {
        $filters = $req->only([
            'id','estatus','calidad','servicio','centro','tl','desde','hasta'
        ]);
        $format = $req->get('format','xlsx'); // csv|xlsx
        $file   = 'reporte_ots_'.now()->format('Ymd_His').'.'.$format;

        return Excel::download(new OtExport($filters, $req->user()), $file);
    }

    public function exportFacturas(Request $req)
    {
        $filters = $req->only(['estatus','servicio','centro','desde','hasta']);
        $format = $req->get('format','xlsx'); // csv|xlsx
        $file   = 'reporte_facturas_'.now()->format('Ymd_His').'.'.$format;

        return Excel::download(new FacturaExport($filters, $req->user()), $file);
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
