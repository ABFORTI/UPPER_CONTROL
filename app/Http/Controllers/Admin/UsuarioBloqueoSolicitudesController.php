<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CentroCosto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UsuarioBloqueoSolicitudesController extends Controller
{
    private const ROLES_CLIENTE = ['Cliente_Supervisor', 'Cliente_Gerente', 'integracion_api'];

    private const ROLES_PROTEGIDOS = [
        'admin', 'coordinador', 'calidad', 'facturacion',
    ];

    public function index(Request $request)
    {
        $q = User::query()
            ->with([
                'roles:name',
                'centro:id,nombre',
                'bloqueadoSolicitudesPor:id,name',
            ])
            ->whereHas('roles', fn ($w) => $w->whereIn('name', self::ROLES_CLIENTE))
            ->when($request->filled('search'), function ($qq) use ($request) {
                $s = '%' . $request->search . '%';
                $qq->where(function ($w) use ($s) {
                    $w->where('name', 'like', $s)
                      ->orWhere('email', 'like', $s);
                });
            })
            ->when($request->filled('centro'), fn ($qq) =>
                $qq->where('centro_trabajo_id', $request->integer('centro'))
            )
            ->when($request->filled('centro_costo'), function ($qq) use ($request) {
                $cc = CentroCosto::find($request->integer('centro_costo'));
                if ($cc) {
                    $qq->where('centro_trabajo_id', (int) $cc->id_centrotrabajo);
                }
            })
            ->when($request->filled('estado'), function ($qq) use ($request) {
                match ($request->estado) {
                    'bloqueados' => $qq->where('bloqueado_solicitudes', true),
                    'activos'    => $qq->where('bloqueado_solicitudes', false),
                    default      => null,
                };
            })
            ->orderBy('name');

        $data = $q->paginate(15)->withQueryString();

        $data->getCollection()->transform(function (User $u) {
            $centrosCosto = $u->centro_trabajo_id
                ? CentroCosto::where('id_centrotrabajo', $u->centro_trabajo_id)
                    ->orderBy('nombre')
                    ->get(['id', 'nombre'])
                : collect();

            return [
                'id'                          => $u->id,
                'name'                        => $u->name,
                'email'                       => $u->email,
                'roles'                       => $u->roles->pluck('name')->values(),
                'centro'                      => $u->centro ? ['id' => $u->centro->id, 'nombre' => $u->centro->nombre] : null,
                'centros_costo'               => $centrosCosto->values(),
                'bloqueado_solicitudes'       => (bool) $u->bloqueado_solicitudes,
                'motivo_bloqueo_solicitudes'  => $u->motivo_bloqueo_solicitudes,
                'bloqueado_solicitudes_en'    => $u->bloqueado_solicitudes_en?->toISOString(),
                'bloqueado_por'               => $u->bloqueadoSolicitudesPor
                    ? ['id' => $u->bloqueadoSolicitudesPor->id, 'name' => $u->bloqueadoSolicitudesPor->name]
                    : null,
            ];
        });

        $centros = \Illuminate\Support\Facades\DB::table('centros_trabajo')
            ->select('id', 'nombre')
            ->orderBy('nombre')
            ->get();

        $centrosCosto = CentroCosto::orderBy('nombre')->get(['id', 'id_centrotrabajo', 'nombre']);

        return Inertia::render('Admin/UsuariosBloqueoSolicitudes/Index', [
            'data'       => $data,
            'filters'    => $request->only(['search', 'centro', 'centro_costo', 'estado']),
            'centros'    => $centros,
            'centrosCosto' => $centrosCosto,
            'urls'       => [
                'bloquear'    => route('admin.usuarios-bloqueo-solicitudes.bloquear', 0),
                'desbloquear' => route('admin.usuarios-bloqueo-solicitudes.desbloquear', 0),
            ],
        ]);
    }

    public function bloquear(Request $request, User $user)
    {
        $request->validate([
            'motivo_bloqueo_solicitudes' => ['required', 'string', 'max:1000'],
        ]);

        if (!$user->hasAnyRole(self::ROLES_CLIENTE)) {
            return back()->withErrors([
                'usuario' => 'Solo se puede bloquear a usuarios con rol Cliente_Supervisor o Cliente_Gerente.',
            ]);
        }

        if ($user->hasAnyRole(self::ROLES_PROTEGIDOS)) {
            return back()->withErrors([
                'usuario' => 'No se puede bloquear a usuarios administrativos.',
            ]);
        }

        $user->update([
            'bloqueado_solicitudes'      => true,
            'motivo_bloqueo_solicitudes' => $request->motivo_bloqueo_solicitudes,
            'bloqueado_solicitudes_en'   => now(),
            'bloqueado_solicitudes_por'  => Auth::id(),
        ]);

        return back()->with('ok', 'Usuario bloqueado para generar solicitudes.');
    }

    public function desbloquear(User $user)
    {
        if (!$user->hasAnyRole(self::ROLES_CLIENTE)) {
            return back()->withErrors([
                'usuario' => 'Solo se puede desbloquear a usuarios con rol Cliente_Supervisor o Cliente_Gerente.',
            ]);
        }

        $user->update([
            'bloqueado_solicitudes'      => false,
            'motivo_bloqueo_solicitudes' => null,
            'bloqueado_solicitudes_en'   => null,
            'bloqueado_solicitudes_por'  => null,
        ]);

        return back()->with('ok', 'Usuario desbloqueado para generar solicitudes.');
    }
}
