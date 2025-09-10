<?php

// app/Http/Controllers/Admin/CentroController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCentroRequest;
use App\Http\Requests\Admin\UpdateCentroRequest;
use App\Models\CentroTrabajo;
use Inertia\Inertia;
use Illuminate\Http\Request;

class CentroController extends Controller
{
    public function index(Request $req)
    {
        $q = CentroTrabajo::query()
            ->when($req->filled('search'), fn($qq)=>$qq->where('nombre','like','%'.$req->search.'%'))
            ->when($req->filled('activo'), fn($qq)=>$qq->where('activo', (bool)$req->boolean('activo')))
            ->orderBy('nombre');

        $data = $q->paginate(12)->withQueryString();

        // stats rápidos
        $totales = [
            'activos' => CentroTrabajo::where('activo',1)->count(),
            'inactivos' => CentroTrabajo::where('activo',0)->count(),
        ];

        return Inertia::render('Admin/Centros/Index', [
            'data'    => $data,
            'filters' => $req->only(['search','activo']),
            'totales' => $totales,
            'urls' => [
                'create' => route('admin.centros.create'),
                'store' => route('admin.centros.store'),
                'edit' => route('admin.centros.edit', 0), // reemplaza 0 en el front
                'update' => route('admin.centros.update', 0), // reemplaza 0 en el front
                'toggle' => route('admin.centros.toggle', 0), // reemplaza 0 en el front
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Centros/Edit', [
            'centro' => null,
        ]);
    }

    public function store(StoreCentroRequest $req)
    {
        $c = CentroTrabajo::create($req->validated());
        // (opcional) activity log
        app(\Spatie\Activitylog\ActivityLogger::class)
            ->useLog('centros')
            ->performedOn($c)
            ->event('crear')
            ->log("Centro {$c->nombre} creado");
        return redirect()->route('admin.centros.index')->with('ok','Centro creado');
    }

    public function edit(CentroTrabajo $centro)
    {
        return Inertia::render('Admin/Centros/Edit', [
            'centro' => $centro,
        ]);
    }

    public function update(UpdateCentroRequest $req, CentroTrabajo $centro)
    {
        $centro->update($req->validated());
        app(\Spatie\Activitylog\ActivityLogger::class)
            ->useLog('centros')
            ->performedOn($centro)
            ->event('editar')
            ->log("Centro {$centro->id} actualizado");
        return redirect()->route('admin.centros.index')->with('ok','Centro actualizado');
    }

    public function toggle(CentroTrabajo $centro)
    {
        // pequeña regla: no permitir desactivar si tiene usuarios activos asignados
        $usuariosActivos = $centro->users()->where('activo',1)->count();
        if ($centro->activo && $usuariosActivos > 0) {
            return back()->withErrors(['activo'=>"No se puede desactivar: hay {$usuariosActivos} usuario(s) activo(s) en este centro."]);
        }
        $centro->update(['activo'=>!$centro->activo]);

        app(\Spatie\Activitylog\ActivityLogger::class)
            ->useLog('centros')
            ->performedOn($centro)
            ->event('toggle')
            ->log("Centro {$centro->id} activo={$centro->activo}");

        return back()->with('ok', $centro->activo ? 'Centro activado' : 'Centro desactivado');
    }
}
