<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- Limpia Service Workers y caches heredados de versiones anteriores del PWA.
             Una vez limpiados (sessionStorage marca la versión), no vuelve a correr. --}}
        <script>
        (function() {
            if (!('serviceWorker' in navigator) && !('caches' in window)) return;
            var VER = 'v{{ filemtime(public_path("build/manifest.json")) }}';
            if (sessionStorage.getItem('uc_sw_clean') === VER) return;
            var jobs = [];
            if ('serviceWorker' in navigator)
                jobs.push(navigator.serviceWorker.getRegistrations().then(function(rs) {
                    return Promise.all(rs.map(function(r) { return r.unregister(); }));
                }));
            if ('caches' in window)
                jobs.push(caches.keys().then(function(ks) {
                    return Promise.all(ks.map(function(k) { return caches.delete(k); }));
                }));
            Promise.all(jobs).then(function() {
                sessionStorage.setItem('uc_sw_clean', VER);
                window.location.reload(true);
            }).catch(function() {
                sessionStorage.setItem('uc_sw_clean', VER);
                window.location.reload(true);
            });
        })();
        </script>

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <!-- Favicon / Window icon -->
    <link rel="icon" type="image/png" href="{{ asset('img/upper_control192.png') }}">
    <link rel="shortcut icon" href="{{ asset('img/upper_control192.png') }}">
    <!-- Explicit Apple touch icons for iOS/Safari (bigger source is ok) -->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('img/upper_control512.png') }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('img/upper_control512.png') }}">
    <!-- Manifest with cache-bust query to help browsers fetch the updated file -->
    <link rel="manifest" href="{{ asset('manifest.json') }}?v=2">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#F5F7FA">
    <meta name="theme-color" content="#F5F7FA" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0B2330" media="(prefers-color-scheme: dark)">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="mobile-web-app-capable" content="yes">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        <script>
            window.__SPLASH_MODE__ = @json(request()->cookie('splash_mode'));
        </script>
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
    <!-- Splash principal: solo login/logout (oculto por defecto; lo controla app.js) -->
    <div id="app-splash" class="fixed inset-0 z-[9999] select-none text-white bg-gradient-to-br from-[#0a1a24] via-[#0b1f2b] to-[#0b2330]" style="display:none; opacity:0;">
            <!-- Glow superior -->
            <div class="pointer-events-none absolute -top-20 left-1/2 -translate-x-1/2 w-[110%] h-40 bg-cyan-500/10 blur-2xl"></div>
            <!-- Red de puntos decorativos -->
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute top-6 left-6 w-2 h-2 bg-orange-400 rounded-full"></div>
                <div class="absolute top-10 left-48 w-[2px] h-[2px] bg-cyan-400 rounded-full"></div>
                <div class="absolute bottom-10 right-10 w-2 h-2 bg-orange-400 rounded-full"></div>
                <div class="absolute bottom-20 right-32 w-[2px] h-[2px] bg-cyan-400 rounded-full"></div>
                <!-- extras naranja -->
                <div class="absolute rounded-full bg-orange-400" style="top:8%; left:12%; width:6px; height:6px"></div>
                <div class="absolute rounded-full bg-orange-400" style="top:14%; left:28%; width:4px; height:4px"></div>
                <div class="absolute rounded-full bg-orange-400" style="top:22%; left:42%; width:5px; height:5px"></div>
                <div class="absolute rounded-full bg-orange-400" style="top:12%; right:14%; width:6px; height:6px"></div>
                <div class="absolute rounded-full bg-orange-400" style="top:30%; right:8%; width:4px; height:4px"></div>
                <div class="absolute rounded-full bg-orange-400" style="top:46%; left:10%; width:5px; height:5px"></div>
                <div class="absolute rounded-full bg-orange-400" style="bottom:28%; left:18%; width:6px; height:6px"></div>
                <div class="absolute rounded-full bg-orange-400" style="bottom:22%; right:22%; width:4px; height:4px"></div>
                <div class="absolute rounded-full bg-orange-400" style="bottom:30%; right:36%; width:5px; height:5px"></div>
                <div class="absolute rounded-full bg-orange-400" style="bottom:36%; left:38%; width:4px; height:4px"></div>
                <div class="absolute rounded-full bg-orange-400" style="bottom:10%; left:12%; width:6px; height:6px"></div>
                <div class="absolute rounded-full bg-orange-400" style="bottom:14%; right:12%; width:5px; height:5px"></div>
            </div>
            <!-- Contenido centrado -->
            <div class="w-full h-full flex items-center justify-center p-6">
                <div class="w-full max-w-5xl mx-auto">
                    <!-- Imagen del dron + globo proporcionada -->
                    <img src="{{ asset('img/upper-drone.png') }}" alt="Upper Logistics Drone" class="mx-auto w-[min(88vw,500px)] h-auto select-none pointer-events-none drop-shadow-[0_20px_60px_rgba(0,0,0,0.45)]" />
                    <!-- Título y subtítulo -->
                    <div class="text-center mt-2">
                        <div class="text-[26px] md:text-[32px] font-bold tracking-[0.22em]">UPPER CONTROL</div>
                        <div class="mt-1 text-sm md:text-base tracking-[0.35em] text-white/80">BY UPPER LOGISTICS</div>
                    </div>
                    <!-- Barra de carga -->
                    <div class="mt-6 flex flex-col items-center">
                        <div class="hidden md:block h-3 mb-1">
                            <div class="flex gap-2 opacity-70">
                                @for($i=0; $i<24; $i++)
                                    <div class="w-1 h-1 rounded-full" style="background:rgba(255,255,255,0.4)"></div>
                                @endfor
                            </div>
                        </div>
                        <div class="w-full max-w-2xl h-3 bg-white/10 rounded-full overflow-hidden border border-white/10">
                            <div id="app-splash-bar" class="h-full rounded-full" style="width:14%; background: linear-gradient(90deg, #ff8a00 0%, #22d3ee 100%);"></div>
                        </div>
                        <div id="app-splash-text" class="mt-3 text-[11px] tracking-widest text-white/80">CARGANDO... POR FAVOR ESPERE</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loader de procesos: para navegaciones y tareas (ligero) -->
        <div id="process-loader" class="fixed inset-0 z-[9998] hidden items-center justify-center bg-black/30">
            <div class="conveyor-wrapper p-6 rounded-xl bg-white/95 text-slate-800 shadow-xl backdrop-blur">
                <div id="process-loader-text" class="text-xs tracking-wider uppercase text-slate-600 text-center mb-3">Procesando...</div>
                <div class="conveyor-belt">
                    <div class="roller left"></div>
                    <div class="roller right"></div>
                    <div class="belt-track"></div>
                    <div class="box box1"></div>
                    <div class="box box2"></div>
                    <div class="box box3"></div>
                </div>
            </div>
        </div>

        @inertia
    </body>
</html>
