<?php

namespace App\Http\Controllers;

use App\Models\CentroTrabajo;
use App\Models\ServicioEmpresa;
use App\Models\SkuServicio;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SkuServicioController extends Controller
{
    public function index(Request $req)
    {
        $u = $req->user();
        $centrosPermitidos = $this->allowedCentroIds($u);

        $q = SkuServicio::with(['centro', 'servicio'])
            ->when(!empty($centrosPermitidos), fn ($qq) => $qq->whereIn('id_centrotrabajo', $centrosPermitidos))
            ->when(empty($centrosPermitidos) && !$u->hasRole('admin'), fn ($qq) => $qq->whereRaw('1=0'))
            ->when($req->filled('centro'), fn ($qq) => $qq->where('id_centrotrabajo', (int) $req->input('centro')))
            ->when($req->filled('sku'), fn ($qq) => $qq->where('sku', 'like', '%' . $req->input('sku') . '%'))
            ->orderByDesc('id');

        $data = $q->paginate(20)->withQueryString()->through(fn ($s) => [
            'id' => $s->id,
            'sku' => $s->sku,
            'centro' => ['id' => $s->id_centrotrabajo, 'nombre' => $s->centro?->nombre],
            'servicio' => ['id' => $s->id_servicio, 'nombre' => $s->servicio?->nombre],
            'created_at' => optional($s->created_at)->format('Y-m-d H:i'),
        ]);

        $centros = $u->hasRole('admin')
            ? CentroTrabajo::select('id', 'nombre')->orderBy('nombre')->get()
            : CentroTrabajo::whereIn('id', $centrosPermitidos)->select('id', 'nombre')->orderBy('nombre')->get();

        return Inertia::render('SkuServicios/Index', [
            'data' => $data,
            'filters' => $req->only(['centro', 'sku']),
            'centros' => $centros,
            'servicios' => ServicioEmpresa::select('id', 'nombre')->orderBy('nombre')->get(),
            'urls' => [
                'index' => route('sku-servicios.index'),
                'store' => route('sku-servicios.store'),
            ],
        ]);
    }

    public function store(Request $req)
    {
        $u = $req->user();
        $validated = $req->validate([
            'id_centrotrabajo' => ['required', 'integer', 'exists:centros_trabajo,id'],
            'sku' => ['required', 'string', 'max:100'],
            'id_servicio' => ['required', 'integer', 'exists:servicios_empresa,id'],
        ]);

        if (!$u->hasRole('admin')) {
            $centrosPermitidos = array_map('intval', $this->allowedCentroIds($u));
            if (!in_array((int) $validated['id_centrotrabajo'], $centrosPermitidos, true)) {
                return back()->withErrors(['id_centrotrabajo' => 'No tienes acceso a ese centro de trabajo.']);
            }
        }

        $sku = trim($validated['sku']);

        SkuServicio::updateOrCreate(
            ['id_centrotrabajo' => $validated['id_centrotrabajo'], 'sku' => $sku],
            ['id_servicio' => $validated['id_servicio'], 'created_by' => $u->id]
        );

        return back()->with('success', 'SKU guardado en el catálogo.');
    }

    public function destroy(Request $req, SkuServicio $skuServicio)
    {
        $u = $req->user();
        if (!$u->hasRole('admin')) {
            $centrosPermitidos = array_map('intval', $this->allowedCentroIds($u));
            if (!in_array((int) $skuServicio->id_centrotrabajo, $centrosPermitidos, true)) {
                abort(403);
            }
        }

        $skuServicio->delete();

        return back()->with('success', 'SKU eliminado del catálogo.');
    }

    private function allowedCentroIds(User $u): array
    {
        if ($u->hasRole('admin')) return [];
        $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn ($v) => (int) $v)->all();
        $primary = (int) ($u->centro_trabajo_id ?? 0);
        if ($primary) $ids[] = $primary;
        return array_values(array_unique(array_filter($ids)));
    }
}
