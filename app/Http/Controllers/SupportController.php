<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class SupportController extends Controller
{
    public function notificacionesIndex(Request $request)
    {
        $items = $request->user()->notifications()->latest()->limit(50)->get()
            ->map(fn($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ]);

        return Inertia::render('Notificaciones/Index', ['items' => $items]);
    }

    public function notificacionesReadAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return back();
    }

    public function notificacionesRead(Request $request, $notificationId)
    {
        $user = $request->user();
        $notification = $user->notifications()->whereKey($notificationId)->firstOrFail();
        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }
        return response()->noContent();
    }

    public function storage(string $path)
    {
        $disk = Storage::disk('public');
        if (strpos($path, 'app/public/') === 0) {
            $path = substr($path, strlen('app/public/'));
        }
        if (! $disk->exists($path)) {
            abort(404);
        }
        return response()->file($disk->path($path));
    }

    public function testRolesDebug(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'roles' => $user->roles->pluck('name'),
            'has_control' => $user->hasRole('control'),
            'has_comercial' => $user->hasRole('comercial'),
            'has_any_role' => $user->hasAnyRole(['admin','coordinador','control','comercial']),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }
}
