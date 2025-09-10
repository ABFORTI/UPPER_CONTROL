<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $u    = $request->user();
        // Ej.: "/upper-control/public" (o cadena vacía si está en raíz)
        $base = rtrim($request->getBaseUrl(), '/');

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $u ? [
                    'id'                 => $u->id,
                    'name'               => $u->name,
                    'email'              => $u->email,
                    'centro_trabajo_id'  => $u->centro_trabajo_id,
                    // Forzamos array para que Vue pueda usar .includes()
                    'roles'              => $u->getRoleNames()->values(),
                    'unread_count'       => $u->unreadNotifications()->count(),
                ] : null,
            ],

            'impersonation' => [
                'active' => (bool) session('impersonated_by'),
                'by'     => session('impersonated_by'),
            ],

            // ⚠️ Usamos 'globals' para no chocar con 'urls' locales de cada página
            'globals' => [
                // Construida con el baseURL por si la app vive en subcarpeta
                'impersonate_leave' => $base.'/admin/impersonate/leave',
            ],

            'urls' => [
                'impersonate_leave' => route('admin.impersonate.leave'),
                'centros_index' => route('admin.centros.index'),
            ],

            // (opcional) errores compartidos
            'errors' => fn () => (object) ($request->session()->get('errors')?->getBag('default')?->toArray() ?? []),
        ]);
    }
}
