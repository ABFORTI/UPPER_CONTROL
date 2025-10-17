<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\CentroTrabajo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class AreaController extends Controller
{
    private function authorizeFromCentro($idCentro)
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) return;
        
        if ($user->hasRole('coordinador')) {
            if ($user->centro_trabajo_id != $idCentro) {
                abort(403, 'No tienes permisos para este centro de trabajo.');
            }
            return;
        }
        
        abort(403, 'No autorizado.');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $centro = request('centro', null);
        
        // Admin ve todas las áreas, coordinador solo las de su centro
        if ($user->hasRole('admin')) {
            // Si se especifica un centro via query, filtrar por él
            if ($centro) {
                $areas = Area::where('id_centrotrabajo', $centro)
                    ->with('centro')
                    ->orderBy('nombre')
                    ->get();
            } else {
                $areas = Area::with('centro')->orderBy('id_centrotrabajo')->orderBy('nombre')->get();
            }
            $centros = CentroTrabajo::orderBy('nombre')->get();
        } else if ($user->hasRole('coordinador')) {
            // El coordinador solo ve su propio centro; ignorar el query param
            $areas = Area::where('id_centrotrabajo', $user->centro_trabajo_id)
                ->with('centro')
                ->orderBy('nombre')
                ->get();
            $centros = CentroTrabajo::where('id', $user->centro_trabajo_id)->get();
            // Forzar $centro para que el frontend muestre el centro correcto
            $centro = $user->centro_trabajo_id;
        } else {
            abort(403);
        }

        return Inertia::render('Areas/Index', [
            'areas' => $areas,
            'centros' => $centros,
            'can' => [
                'create' => $user->hasAnyRole(['admin', 'coordinador']),
                'edit' => $user->hasAnyRole(['admin', 'coordinador']),
            ],
            'filters' => [ 'centro' => $centro ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'id_centrotrabajo' => ['required', 'integer', 'exists:centros_trabajo,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['boolean'],
        ]);

        $this->authorizeFromCentro($data['id_centrotrabajo']);

        Area::create($data);

        return redirect()->route('areas.index')
            ->with('success', 'Área creada exitosamente.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Area $area)
    {
        $this->authorizeFromCentro($area->id_centrotrabajo);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['boolean'],
        ]);

        $area->update($data);

        return redirect()->route('areas.index')
            ->with('success', 'Área actualizada exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Area $area)
    {
        $this->authorizeFromCentro($area->id_centrotrabajo);

        $area->delete();

        return redirect()->route('areas.index')
            ->with('success', 'Área eliminada exitosamente.');
    }
}
