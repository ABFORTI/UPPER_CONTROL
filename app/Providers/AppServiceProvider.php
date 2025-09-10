<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\ActivityLogger;
use Spatie\Activitylog\Models\Activity;



class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        \Inertia\Inertia::share('auth', function () {
            $u = auth()->user();
            if (!$u) return ['user' => null];
            return [
                'user' => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'roles' => $u->getRoleNames()->toArray(),
                ]
            ];
        });

        // Agrega IP, UA y centro en cada actividad (si hay usuario)
        app(ActivityLogger::class)->tap(function (Activity $activity) {
            $u = Auth::user();
            if ($u) {
                $activity->causer_id = $u->getKey();
                $activity->causer_type = get_class($u);
                $activity->properties = collect($activity->properties)
                    ->merge([
                        'centro_trabajo_id' => $u->centro_trabajo_id,
                        'ip'  => request()->ip(),
                        'ua'  => substr((string)request()->userAgent(), 0, 255),
                    ]);
            }
        });
    }

    
}
