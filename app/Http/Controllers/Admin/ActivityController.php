<?php
// app/Http/Controllers/Admin/ActivityController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityController extends Controller
{
    public function index(Request $req)
    {
        $desde   = $req->date('desde') ?: now()->subDays(30)->startOfDay();
        $hasta   = $req->date('hasta') ?: now()->endOfDay();
        $log     = $req->string('log')->toString();       // 'ordenes','solicitudes','facturas','usuarios', etc.
        $event   = $req->string('event')->toString();     // 'generar_ot','aprobar','estatus', etc.
        $userId  = $req->integer('user') ?: null;
        $centro  = $req->integer('centro') ?: null;
        $search  = $req->string('q')->toString();

        $q = Activity::with('causer')
            ->whereBetween('created_at', [$desde, $hasta])
            ->when($log,    fn($qq,$v)=>$qq->where('log_name',$v))
            ->when($event,  fn($qq,$v)=>$qq->where('event',$v))
            ->when($userId, fn($qq,$v)=>$qq->where('causer_id',$v))
            ->when($centro, fn($qq,$v)=>$qq->where('properties->centro_trabajo_id', $v))
            ->when($search, fn($qq,$v)=>$qq->where(fn($w)=>$w
                ->where('description','like',"%$v%")
                ->orWhere('properties','like',"%$v%")
            ))
            ->orderByDesc('id');

        $data = $q->paginate(20)->withQueryString();

        $centros = \DB::table('centros_trabajo')->select('id','nombre')->orderBy('nombre')->get();
        $usuarios = \DB::table('users')->select('id','name')->orderBy('name')->get();
        $logs = ['ordenes','solicitudes','facturas','usuarios'];
        $events = ['generar_ot','asignar_tl','avance','calidad_validar','calidad_rechazar','cliente_autoriza','crear_factura','estatus','aprobar','rechazar','crear','editar','toggle','reset_password'];

        return Inertia::render('Admin/Activity/Index', [
            'data'    => $data,
            'filters' => $req->only(['desde','hasta','log','event','user','centro','q']),
            'centros' => $centros,
            'usuarios'=> $usuarios,
            'logs'    => $logs,
            'events'  => $events,
            'urls'    => [
                'index'  => route('admin.activity.index'),
                'export' => route('admin.activity.export', $req->query()),
            ],
        ]);
    }

    public function export(Request $req): StreamedResponse
    {
        // Reutiliza mismos filtros que index
        $req2 = Request::create(route('admin.activity.index'), 'GET', $req->query());
        app()->instance('request', $req2); // para usar iguales helpers

        $desde = $req->date('desde') ?: now()->subDays(30)->startOfDay();
        $hasta = $req->date('hasta') ?: now()->endOfDay();

        $rows = Activity::with('causer')
            ->whereBetween('created_at', [$desde, $hasta])
            ->when($req->log,    fn($qq,$v)=>$qq->where('log_name',$v))
            ->when($req->event,  fn($qq,$v)=>$qq->where('event',$v))
            ->when($req->user,   fn($qq,$v)=>$qq->where('causer_id',$v))
            ->when($req->centro, fn($qq,$v)=>$qq->where('properties->centro_trabajo_id', $v))
            ->when($req->q,      fn($qq,$v)=>$qq->where(fn($w)=>$w
                ->where('description','like',"%$v%")
                ->orWhere('properties','like',"%$v%")
            ))
            ->orderBy('id')
            ->get(['id','log_name','event','description','subject_type','subject_id','causer_id','properties','created_at']);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID','Fecha','Log','Evento','Usuario','Subject','Subject ID','Centro','Descripción','Propiedades']);
            foreach ($rows as $a) {
                fputcsv($out, [
                    $a->id,
                    $a->created_at,
                    $a->log_name,
                    $a->event,
                    optional($a->causer)->name,
                    class_basename($a->subject_type),
                    $a->subject_id,
                    data_get($a->properties,'centro_trabajo_id'),
                    $a->description,
                    json_encode($a->properties),
                ]);
            }
            fclose($out);
        }, 'activity.csv', ['Content-Type'=>'text/csv']);
    }
}
