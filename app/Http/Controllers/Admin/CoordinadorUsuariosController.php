<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CentroTrabajo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class CoordinadorUsuariosController extends Controller
{
    public function index(Request $request)
    {
        $centros = CentroTrabajo::orderBy('nombre')->get(['id', 'nombre', 'prefijo']);

        $selectedCentroId = (int) ($request->integer('centro_trabajo_id') ?? 0);
        if ($selectedCentroId === 0) {
            $selectedCentroId = (int) ($centros->first()?->id ?? 0);
        }

        // Coordinadores de equipo del centro seleccionado
        $coordinadores = User::role('coordinador_equipo')
            ->where(function ($q) use ($selectedCentroId) {
                $q->where('centro_trabajo_id', $selectedCentroId)
                  ->orWhereHas('centros', fn($w) => $w->where('centro_trabajo_id', $selectedCentroId));
            })
            ->with(['usuariosAsignados' => fn($q) => $q->select('users.id')])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Todos los usuarios del centro (para asignarles coordinador)
        $usuariosCentro = User::where(function ($q) use ($selectedCentroId) {
                $q->where('centro_trabajo_id', $selectedCentroId)
                  ->orWhereHas('centros', fn($w) => $w->where('centro_trabajo_id', $selectedCentroId));
            })
            ->where('activo', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $selectedCoordinadorId = (int) ($request->integer('coordinador_id') ?? 0);
        if ($selectedCoordinadorId === 0) {
            $selectedCoordinadorId = (int) ($coordinadores->first()?->id ?? 0);
        }

        // Ids de usuarios asignados al coordinador seleccionado
        $asignadosIds = [];
        if ($selectedCoordinadorId) {
            $asignadosIds = DB::table('coordinador_usuarios')
                ->where('coordinador_id', $selectedCoordinadorId)
                ->pluck('usuario_id')
                ->map(fn($v) => (int) $v)
                ->values()
                ->all();
        }

        return Inertia::render('Admin/CoordinadorUsuarios/Index', [
            'centros'              => $centros,
            'selectedCentroId'     => $selectedCentroId,
            'coordinadores'        => $coordinadores->map(fn($c) => [
                'id'    => (int) $c->id,
                'name'  => (string) $c->name,
                'email' => (string) $c->email,
            ])->values(),
            'selectedCoordinadorId' => $selectedCoordinadorId,
            'usuariosCentro'       => $usuariosCentro->map(fn($u) => [
                'id'    => (int) $u->id,
                'name'  => (string) $u->name,
                'email' => (string) $u->email,
            ])->values(),
            'asignadosIds'         => $asignadosIds,
            'urls'                 => [
                'index'  => route('admin.coordinadores.usuarios.index'),
                'update' => $selectedCoordinadorId
                    ? route('admin.coordinadores.usuarios.update', $selectedCoordinadorId)
                    : null,
            ],
        ]);
    }

    public function update(Request $request, User $coordinador)
    {
        $request->validate([
            'usuario_ids'   => ['nullable', 'array'],
            'usuario_ids.*' => ['integer', 'exists:users,id'],
        ]);

        if (!$coordinador->hasRole('coordinador_equipo')) {
            abort(422, 'El usuario seleccionado no tiene el rol coordinador_equipo.');
        }

        $ids = collect($request->input('usuario_ids', []))
            ->map(fn($v) => (int) $v)
            ->unique()
            ->filter(fn($v) => $v > 0)
            ->values()
            ->all();

        $sync = [];
        foreach ($ids as $uid) {
            $sync[$uid] = ['created_at' => now(), 'updated_at' => now()];
        }

        $coordinador->usuariosAsignados()->sync($sync);

        return redirect()
            ->route('admin.coordinadores.usuarios.index', [
                'centro_trabajo_id' => $request->integer('centro_trabajo_id') ?: null,
                'coordinador_id'    => $coordinador->id,
            ])
            ->with('ok', 'Usuarios asignados actualizados correctamente.');
    }
}
