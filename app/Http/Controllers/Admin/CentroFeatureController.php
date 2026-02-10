<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CentroTrabajo;
use App\Models\Feature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CentroFeatureController extends Controller
{
    /**
     * Pantalla admin para activar/desactivar funcionalidades por centro.
     *
     * - Selecciona un centro
     * - Muestra todas las features del catálogo
     * - Permite habilitar/deshabilitar (checkbox)
     */
    public function index(Request $request)
    {
        $centros = CentroTrabajo::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'prefijo', 'activo']);

        $selectedCentroId = (int) ($request->integer('centro_trabajo_id') ?? 0);
        if ($selectedCentroId === 0) {
            $selectedCentroId = (int) ($centros->first()?->id ?? 0);
        }

        $centro = $selectedCentroId
            ? CentroTrabajo::with('features')->findOrFail($selectedCentroId)
            : null;

        $allFeatures = Feature::query()->orderBy('nombre')->get(['id', 'key', 'nombre', 'descripcion']);

        $enabledById = collect();
        if ($centro) {
            $enabledById = $centro->features
                ->mapWithKeys(fn ($f) => [(int) $f->id => (bool) $f->pivot?->enabled]);
        }

        $features = $allFeatures->map(function ($f) use ($enabledById) {
            return [
                'id' => (int) $f->id,
                'key' => (string) $f->key,
                'nombre' => (string) $f->nombre,
                'descripcion' => (string) ($f->descripcion ?? ''),
                'enabled' => (bool) ($enabledById[(int) $f->id] ?? false),
            ];
        })->values();

        return Inertia::render('Admin/CentroFeatures/Index', [
            'centros' => $centros,
            'selectedCentroId' => $selectedCentroId,
            'features' => $features,
            'urls' => [
                'index' => route('admin.centros.features.index'),
                'update' => route('admin.centros.features.update', $selectedCentroId ?: 0), // reemplaza 0 en front si aplica
            ],
        ]);
    }

    public function update(Request $request, CentroTrabajo $centro)
    {
        $validated = $request->validate([
            'enabled_features' => ['array'],
            'enabled_features.*' => ['integer', 'exists:features,id'],
        ]);

        $enabledIds = collect($validated['enabled_features'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        $allIds = Feature::query()->pluck('id')->map(fn ($v) => (int) $v);

        $sync = [];
        foreach ($allIds as $featureId) {
            $sync[$featureId] = ['enabled' => $enabledIds->contains($featureId)];
        }

        DB::transaction(function () use ($centro, $sync) {
            // Nota: usamos sync con data para asegurar que existan todas las filas de pivote.
            $centro->features()->sync($sync);
        });

        return redirect()
            ->route('admin.centros.features.index', ['centro_trabajo_id' => $centro->id])
            ->with('ok', 'Funcionalidades actualizadas.');
    }
}
