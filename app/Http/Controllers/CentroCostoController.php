<?php

namespace App\Http\Controllers;

use App\Models\CentroCosto;
use App\Models\CentroTrabajo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class CentroCostoController extends Controller
{
    private function authorizeFromCentro($idCentro)
    {
    /** @var \App\Models\User $user */
    $user = Auth::user();
        if ($user->hasRole('admin')) return;

        if ($user->hasAnyRole(['coordinador', 'control', 'comercial'])) {
            if ($user->hasRole('coordinador') && (int)$user->centro_trabajo_id !== (int)$idCentro) {
                abort(403, 'No tienes permisos para este centro de trabajo.');
            }
            if ($user->hasAnyRole(['control', 'comercial'])) {
                $centrosIds = $user->centros()->pluck('centros_trabajo.id')->toArray();
                if (!in_array((int)$idCentro, array_map('intval',$centrosIds), true)) {
                    abort(403, 'No tienes permisos para este centro de trabajo.');
                }
            }
            return;
        }

        abort(403, 'No autorizado.');
    }

    public function index()
    {
    /** @var \App\Models\User $user */
    $user = Auth::user();
        $centro = request('centro', null);

        if ($user->hasRole('admin')) {
            if ($centro) {
                $items = CentroCosto::where('id_centrotrabajo', $centro)
                    ->with('centro')->orderBy('nombre')->get();
            } else {
                $items = CentroCosto::with('centro')->orderBy('id_centrotrabajo')->orderBy('nombre')->get();
            }
            $centros = CentroTrabajo::orderBy('nombre')->get();
        } elseif ($user->hasRole('coordinador')) {
            $items = CentroCosto::where('id_centrotrabajo', $user->centro_trabajo_id)
                ->with('centro')->orderBy('nombre')->get();
            $centros = CentroTrabajo::where('id',$user->centro_trabajo_id)->get();
            $centro = $user->centro_trabajo_id;
        } elseif ($user->hasAnyRole(['control','comercial'])) {
            $centrosIds = $user->centros()->pluck('centros_trabajo.id')->toArray();
            if (empty($centrosIds)) abort(403, 'No tienes centros de trabajo asignados.');
            if ($centro && in_array((int)$centro, array_map('intval',$centrosIds), true)) {
                $items = CentroCosto::where('id_centrotrabajo', $centro)
                    ->with('centro')->orderBy('nombre')->get();
            } else {
                $items = CentroCosto::whereIn('id_centrotrabajo', $centrosIds)
                    ->with('centro')->orderBy('id_centrotrabajo')->orderBy('nombre')->get();
            }
            $centros = CentroTrabajo::whereIn('id', $centrosIds)->orderBy('nombre')->get();
        } else {
            abort(403);
        }

        return Inertia::render('CentrosCostos/Index', [
            'items' => $items,
            'centros' => $centros,
            'can' => [
                'create' => $user->hasAnyRole(['admin','coordinador','control','comercial']),
                'edit'   => $user->hasAnyRole(['admin','coordinador','control','comercial']),
            ],
            'filters' => ['centro' => $centro],
            'urls' => [ 'index' => route('centros_costos.index') ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_centrotrabajo' => ['required','integer','exists:centros_trabajo,id'],
            'nombre' => ['required','string','max:255'],
            'activo' => ['boolean'],
        ]);
        $this->authorizeFromCentro($data['id_centrotrabajo']);
        CentroCosto::create($data);
        return redirect()->route('centros_costos.index')->with('success','Centro de costo creado.');
    }

    public function update(Request $request, CentroCosto $centroCosto)
    {
        $this->authorizeFromCentro($centroCosto->id_centrotrabajo);
        $data = $request->validate([
            'nombre' => ['required','string','max:255'],
            'activo' => ['boolean'],
        ]);
        $centroCosto->update($data);
        return redirect()->route('centros_costos.index')->with('success','Centro de costo actualizado.');
    }

    public function destroy(CentroCosto $centroCosto)
    {
        $this->authorizeFromCentro($centroCosto->id_centrotrabajo);
        $centroCosto->delete();
        return redirect()->route('centros_costos.index')->with('success','Centro de costo eliminado.');
    }
}
