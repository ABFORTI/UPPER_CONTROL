<?php

// app/Http/Controllers/Admin/UserController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $req) {
        $q = User::query()
            ->with('roles:name') // spatie
            ->when($req->filled('role'), fn($qq)=>$qq->whereHas('roles', fn($w)=>$w->where('name',$req->role)))
            ->when($req->filled('centro'), fn($qq)=>$qq->where('centro_trabajo_id',$req->integer('centro')))
            ->when($req->filled('search'), fn($qq)=>$qq->where(function($w) use($req){
                $s = '%'.$req->search.'%';
                $w->where('name','like',$s)->orWhere('email','like',$s);
            }))
            ->orderBy('name');

        $data = $q->paginate(12)->withQueryString();

        $centros = \DB::table('centros_trabajo')->select('id','nombre')->orderBy('nombre')->get();
        $roles = ['admin','coordinador','team_leader','calidad','facturacion','cliente'];

        // Agregar la URL de impersonate a cada usuario
        $data->getCollection()->transform(function ($u) {
            return array_merge($u->toArray(), [
                'urls' => [
                    'impersonate' => route('admin.users.impersonate', $u->id),
                ],
            ]);
        });

        return Inertia::render('Admin/Users/Index', [
            'data'    => $data,
            'filters' => $req->only(['role','centro','search']),
            'centros' => $centros,
            'roles'   => $roles,
        ]);
    }

    public function create() {
        return Inertia::render('Admin/Users/Edit', [
            'user'    => null,
            'centros' => \DB::table('centros_trabajo')->select('id','nombre')->orderBy('nombre')->get(),
            'roles'   => ['admin','coordinador','team_leader','calidad','facturacion','cliente'],
        ]);
    }

    public function store(StoreUserRequest $req) {
        $u = User::create([
            'name'  => $req->name,
            'email' => $req->email,
            'phone' => $req->phone,
            'centro_trabajo_id' => $req->centro_trabajo_id,
            'password' => Hash::make($req->password),
            'activo'   => true,
        ]);
        $u->syncRoles([$req->role]);

        $this->act('usuarios')->performedOn($u)->event('crear')->log("Usuario {$u->email} creado");

        return redirect()->route('admin.users.index')->with('ok','Usuario creado');
    }

    public function edit(User $user) {
        return Inertia::render('Admin/Users/Edit', [
            'user'    => [
                'id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'phone'=>$user->phone,
                'centro_trabajo_id'=>$user->centro_trabajo_id,'role'=>$user->getRoleNames()->first(),
                'activo'=>$user->activo,
            ],
            'centros' => \DB::table('centros_trabajo')->select('id','nombre')->orderBy('nombre')->get(),
            'roles'   => ['admin','coordinador','team_leader','calidad','facturacion','cliente'],
        ]);
    }

    public function update(UpdateUserRequest $req, User $user) {
        // No dejar que el admin se desasigne a sí mismo el rol admin
        $isSelf = $req->user()->id === $user->id;
        if ($isSelf && $req->role !== 'admin') {
            return back()->withErrors(['role'=>'No puedes quitarte el rol de admin a ti mismo.']);
        }

        $user->update([
            'name'  => $req->name,
            'email' => $req->email,
            'phone' => $req->phone,
            'centro_trabajo_id' => $req->centro_trabajo_id,
            // password opcional
            'password' => $req->filled('password') ? Hash::make($req->password) : $user->password,
        ]);
        $user->syncRoles([$req->role]);

        $this->act('usuarios')->performedOn($user)->event('editar')->log("Usuario {$user->email} actualizado");

        return redirect()->route('admin.users.index')->with('ok','Usuario actualizado');
    }

    public function toggleActive(User $user) {
        // No permitir desactivarse a sí mismo
        if (auth()->id() === $user->id) return back()->withErrors(['activo'=>'No puedes desactivarte a ti mismo.']);
        $user->update(['activo'=>!$user->activo]);

        $this->act('usuarios')->performedOn($user)->event('toggle')->log("Usuario {$user->email} activo={$user->activo}");

        return back()->with('ok', $user->activo ? 'Usuario activado' : 'Usuario desactivado');
    }

    public function resetPassword(User $user) {
        if (auth()->id() === $user->id) return back()->withErrors(['password'=>'Usa tu perfil para cambiar tu propio password.']);
        $tmp = str()->password(10);
        $user->update(['password'=>Hash::make($tmp)]);

        $this->act('usuarios')->performedOn($user)->event('reset_password')->log("Password reseteado para {$user->email}");

        // Si quieres, notifica por email o notificación interna con el temporal
        return back()->with('ok',"Password temporal: {$tmp}");
    }

    private function act(string $log)
    {
        return app(\Spatie\Activitylog\ActivityLogger::class)->useLog($log);
    }
}

