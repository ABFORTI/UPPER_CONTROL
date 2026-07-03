<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManualRequest;
use App\Models\Manual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class ManualController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('admin');

        $manuales = Manual::query()
            ->with(['roles:id,manual_id,role', 'uploader:id,name'])
            ->when(! $isAdmin, fn ($query) => $query->visibleToUser($user))
            ->latest()
            ->get()
            ->map(fn (Manual $manual) => $this->manualPayload($manual));

        return Inertia::render('Manuales/Index', [
            'manuales' => $manuales,
            'can' => [
                'create' => $isAdmin,
                'delete' => $isAdmin,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Manuales/Create', [
            'roles' => Role::query()
                ->orderBy('name')
                ->get(['name'])
                ->map(fn (Role $role) => ['name' => $role->name])
                ->values(),
            'maxUploadMb' => 20,
        ]);
    }

    public function store(StoreManualRequest $request)
    {
        $data = $request->validated();

        $manual = Manual::create([
            'titulo' => trim((string) $data['titulo']),
            'descripcion' => $this->cleanDescription($data['descripcion'] ?? null),
            'archivo_pdf' => $request->file('archivo_pdf')->store('manuales', 'public'),
            'visible_para_todos' => (bool) ($data['visible_para_todos'] ?? false),
            'uploaded_by' => $request->user()->id,
        ]);

        if (! $manual->visible_para_todos) {
            $roles = collect($data['roles'] ?? [])
                ->filter()
                ->unique()
                ->map(fn (string $role) => ['role' => $role])
                ->values()
                ->all();

            $manual->roles()->createMany($roles);
        }

        return redirect()->route('manuales.index')->with('ok', 'Manual subido correctamente.');
    }

    public function destroy(Manual $manual)
    {
        if ($manual->archivo_pdf) {
            Storage::disk('public')->delete($manual->archivo_pdf);
        }

        $manual->delete();

        return back()->with('ok', 'Manual eliminado correctamente.');
    }

    public function pdf(Request $request, Manual $manual)
    {
        if (! $manual->isVisibleTo($request->user())) {
            abort(403);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($manual->archivo_pdf)) {
            abort(404);
        }

        return response()->file($disk->path($manual->archivo_pdf), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($manual->archivo_pdf) . '"',
        ]);
    }

    private function manualPayload(Manual $manual): array
    {
        return [
            'id' => $manual->id,
            'titulo' => $manual->titulo,
            'descripcion' => $manual->descripcion,
            'visible_para_todos' => (bool) $manual->visible_para_todos,
            'roles' => $manual->roles->pluck('role')->values()->all(),
            'uploaded_by_name' => $manual->uploader?->name,
            'created_at' => optional($manual->created_at)?->format('Y-m-d H:i'),
            'pdf_url' => route('manuales.pdf', $manual),
        ];
    }

    private function cleanDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $value = trim($description);

        return $value === '' ? null : strip_tags($value);
    }
}
