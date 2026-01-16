<?php

// app/Http/Controllers/Admin/UserController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $req)
    {
        $q = User::query()
            ->with(['roles:name', 'centro:id,nombre', 'centros:id,nombre']) // cargar nombres de centros
            ->when($req->filled('role'), fn ($qq) => $qq->whereHas('roles', fn ($w) => $w->where('name', $req->role)))
            ->when($req->filled('centro'), fn ($qq) => $qq->where('centro_trabajo_id', $req->integer('centro')))
            ->when($req->filled('search'), fn ($qq) => $qq->where(function ($w) use ($req) {
                $s = '%' . $req->search . '%';
                $w->where('name', 'like', $s)->orWhere('email', 'like', $s);
            }))
            ->orderBy('name');

        $data = $q->paginate(12)->withQueryString();

    $centros = DB::table('centros_trabajo')->select('id', 'nombre')->orderBy('nombre')->get();
    $roles = Role::query()->orderBy('name')->pluck('name');

        // Mapear datos extra para la tabla (centro principal y múltiples)
        $data->getCollection()->transform(function ($u) {
            $centrosNombres = collect($u->centros ?? [])->pluck('nombre')->values();
            return array_merge($u->toArray(), [
                'centro_nombre' => $u->centro->nombre ?? null,
                'centros_nombres' => $centrosNombres,
                'urls' => [
                    'impersonate' => route('admin.users.impersonate', $u->id),
                ],
            ]);
        });

        return Inertia::render('Admin/Users/Index', [
            'data' => $data,
            'filters' => $req->only(['role', 'centro', 'search']),
            'centros' => $centros,
            'roles' => $roles,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => null,
            'centros' => DB::table('centros_trabajo')->select('id', 'nombre')->orderBy('nombre')->get(),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(StoreUserRequest $req)
    {
        $data = $req->validated();

        $u = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'centro_trabajo_id' => $data['centro_trabajo_id'],
            'password' => Hash::make($data['password']),
            'activo' => true,
        ]);

        $roles = $data['roles'];
        $u->syncRoles($roles);

        // Asignaciones múltiples (opcional)
        if (!empty($data['centros_ids']) && is_array($data['centros_ids'])) {
            $u->centros()->sync($data['centros_ids']);
        } else {
            $u->centros()->detach();
        }

        $this->act('usuarios')->performedOn($u)->event('crear')->log("Usuario {$u->email} creado");

        return redirect()->route('admin.users.index')->with('ok', 'Usuario creado');
    }

    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'centro_trabajo_id' => $user->centro_trabajo_id,
                'roles' => $user->getRoleNames()->values()->toArray(),
                'activo' => $user->activo,
                'centros_ids' => $user->centros()->pluck('centros_trabajo.id')->toArray(),
            ],
            'centros' => DB::table('centros_trabajo')->select('id', 'nombre')->orderBy('nombre')->get(),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function update(UpdateUserRequest $req, User $user)
    {
    $authId = Auth::id();
    $data = $req->validated();
    $isSelf = $authId !== null && $authId === $user->id;
        if ($isSelf && $user->hasRole('admin') && !in_array('admin', $data['roles'] ?? [], true)) {
            return back()->withErrors(['roles' => 'No puedes quitarte el rol de admin a ti mismo.']);
        }

        $update = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'centro_trabajo_id' => $data['centro_trabajo_id'],
        ];
        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }
        $user->update($update);
        $user->syncRoles($data['roles']);

        if (!empty($data['centros_ids']) && is_array($data['centros_ids'])) {
            $user->centros()->sync($data['centros_ids']);
        } else {
            $user->centros()->detach();
        }

        $this->act('usuarios')->performedOn($user)->event('editar')->log("Usuario {$user->email} actualizado");

        return redirect()->route('admin.users.index')->with('ok', 'Usuario actualizado');
    }

    public function toggleActive(User $user)
    {
        // No permitir desactivarse a sí mismo
        if (Auth::id() !== null && Auth::id() === $user->id) {
            return back()->withErrors(['activo' => 'No puedes desactivarte a ti mismo.']);
        }
        $user->update(['activo' => !$user->activo]);

        $this->act('usuarios')->performedOn($user)->event('toggle')->log("Usuario {$user->email} activo={$user->activo}");

        return back()->with('ok', $user->activo ? 'Usuario activado' : 'Usuario desactivado');
    }

    public function resetPassword(User $user)
    {
        if (Auth::id() !== null && Auth::id() === $user->id) {
            return back()->withErrors(['password' => 'Usa tu perfil para cambiar tu propio password.']);
        }

        $tmp = str()->password(10);
        $user->update(['password' => Hash::make($tmp)]);

        $this->act('usuarios')->performedOn($user)->event('reset_password')->log("Password reseteado para {$user->email}");

        return back()->with('ok', "Password temporal: {$tmp}");
    }

    private function act(string $log)
    {
        return app(\Spatie\Activitylog\ActivityLogger::class)->useLog($log);
    }
}

