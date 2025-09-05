<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;


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
    }

    
}
