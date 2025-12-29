<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use App\Models\CentroTrabajo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class MarcaController extends Controller
{
    private function authorizeFromCentro($idCentro)
    {
    /** @var \App\Models\User $user */
    $user = Auth::user();
    if ($user->hasRole('admin')) return; // gerente_upper no puede escribir

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
                $items = Marca::where('id_centrotrabajo', $centro)
                    ->with('centro')->orderBy('nombre')->get();
            } else {
                $items = Marca::with('centro')->orderBy('id_centrotrabajo')->orderBy('nombre')->get();
            }
            $centros = CentroTrabajo::orderBy('nombre')->get();
        } elseif ($user->hasRole('gerente_upper')) {
            $centrosIds = $user->centros()->pluck('centros_trabajo.id')->toArray();
            $centrosIds = array_map('intval',$centrosIds);
            if (empty($centrosIds)) abort(403, 'No tienes centros de trabajo asignados.');
            if ($centro && in_array((int)$centro, $centrosIds, true)) {
                $items = Marca::where('id_centrotrabajo', (int)$centro)
                    ->with('centro')->orderBy('nombre')->get();
            } else {
                $items = Marca::whereIn('id_centrotrabajo', $centrosIds)
                    ->with('centro')->orderBy('id_centrotrabajo')->orderBy('nombre')->get();
            }
            $centros = CentroTrabajo::whereIn('id', $centrosIds)->orderBy('nombre')->get();
        } elseif ($user->hasRole('coordinador')) {
            $items = Marca::where('id_centrotrabajo', $user->centro_trabajo_id)
                ->with('centro')->orderBy('nombre')->get();
            $centros = CentroTrabajo::where('id',$user->centro_trabajo_id)->get();
            $centro = $user->centro_trabajo_id;
        } elseif ($user->hasAnyRole(['control','comercial'])) {
            $centrosIds = $user->centros()->pluck('centros_trabajo.id')->toArray();
            if (empty($centrosIds)) abort(403, 'No tienes centros de trabajo asignados.');
            if ($centro && in_array((int)$centro, array_map('intval',$centrosIds), true)) {
                $items = Marca::where('id_centrotrabajo', $centro)
                    ->with('centro')->orderBy('nombre')->get();
            } else {
                $items = Marca::whereIn('id_centrotrabajo', $centrosIds)
                    ->with('centro')->orderBy('id_centrotrabajo')->orderBy('nombre')->get();
            }
            $centros = CentroTrabajo::whereIn('id', $centrosIds)->orderBy('nombre')->get();
        } else {
            abort(403);
        }

        return Inertia::render('Marcas/Index', [
            'items' => $items,
            'centros' => $centros,
            'can' => [
                'create' => $user->hasAnyRole(['admin','coordinador','control','comercial']),
                'edit'   => $user->hasAnyRole(['admin','coordinador','control','comercial']),
            ],
            'filters' => ['centro' => $centro],
            'urls' => [ 'index' => route('marcas.index') ],
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
        Marca::create($data);
        return redirect()->route('marcas.index')->with('success','Marca creada.');
    }

    public function update(Request $request, Marca $marca)
    {
        $this->authorizeFromCentro($marca->id_centrotrabajo);
        $data = $request->validate([
            'nombre' => ['required','string','max:255'],
            'activo' => ['boolean'],
        ]);
        $marca->update($data);
        return redirect()->route('marcas.index')->with('success','Marca actualizada.');
    }

    public function destroy(Marca $marca)
    {
        $this->authorizeFromCentro($marca->id_centrotrabajo);
        $marca->delete();
        return redirect()->route('marcas.index')->with('success','Marca eliminada.');
    }
}
