<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\{
    ProfileController,
    SolicitudController,
    OrdenController,
    CalidadController,
    ClienteController,
    FacturaController,
    DashboardController,
    PrecioController,
    EvidenciaController
};

// Home -> Dashboard (el dashboard está protegido con 'auth')
Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

/* ===========================
 |  DASHBOARD & NOTIFICACIONES
 * =========================== */
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/export/ots', [DashboardController::class, 'exportOts'])->name('dashboard.export.ots');
    Route::get('/dashboard/export/facturas', [DashboardController::class, 'exportFacturas'])->name('dashboard.export.facturas');

    Route::get('/notificaciones', function () {
        return inertia('Notificaciones/Index', [
            'items' => auth()->user()->notifications()->latest()->limit(50)->get()
                ->map(fn($n)=>[
                    'id'=>$n->id,
                    'type'=>$n->type,
                    'data'=>$n->data,
                    'read_at'=>$n->read_at,
                    'created_at'=>$n->created_at,
                ]),
        ]);
    })->name('notificaciones.index');

    Route::post('/notificaciones/read-all', function () {
        auth()->user()->unreadNotifications->markAsRead();
        return back();
    })->name('notificaciones.read_all');
});

/* ==========
 |  PRECIOS
 * ========== */
Route::middleware(['auth','role:admin|coordinador'])->group(function () {
    Route::get('/precios', [PrecioController::class,'index'])->name('precios.index');
    Route::post('/precios/guardar', [PrecioController::class,'guardar'])->name('precios.guardar');
});

/* ===============
 |  SOLICITUDES
 * =============== */
Route::middleware('auth')->group(function () {
    Route::get('/solicitudes', [SolicitudController::class,'index'])->name('solicitudes.index');
    Route::get('/solicitudes/create', [SolicitudController::class,'create'])->name('solicitudes.create');
    Route::post('/solicitudes', [SolicitudController::class,'store'])->name('solicitudes.store');
    Route::get('/solicitudes/{solicitud}', [SolicitudController::class,'show'])->name('solicitudes.show');

    Route::post('/solicitudes/{solicitud}/aprobar', [SolicitudController::class,'aprobar'])
        ->middleware('role:coordinador|admin')->name('solicitudes.aprobar');
    Route::post('/solicitudes/{solicitud}/rechazar', [SolicitudController::class,'rechazar'])
        ->middleware('role:coordinador|admin')->name('solicitudes.rechazar');

    // Generar OT desde solicitud
    Route::get('/solicitudes/{solicitud}/generar-ot', [OrdenController::class,'createFromSolicitud'])
        ->name('ordenes.createFromSolicitud');
    Route::post('/solicitudes/{solicitud}/generar-ot', [OrdenController::class,'storeFromSolicitud'])
        ->name('ordenes.storeFromSolicitud');
});

/* ==========
 |  ÓRDENES
 * ========== */
Route::middleware('auth')->group(function () {
    Route::get('/ordenes', [OrdenController::class,'index'])->name('ordenes.index');
    Route::get('/ordenes/{orden}', [OrdenController::class,'show'])->name('ordenes.show');

    Route::patch('/ordenes/{orden}/asignar-tl', [OrdenController::class,'asignarTL'])->name('ordenes.asignarTL');
    Route::post('/ordenes/{orden}/avances', [OrdenController::class,'registrarAvance'])->name('ordenes.avances.store');

    // PDFs
    Route::get('/ordenes/{orden}/pdf',  [OrdenController::class,'pdf'])->name('ordenes.pdf');

    // Evidencias
    Route::post('/ordenes/{orden}/evidencias', [EvidenciaController::class,'store'])->name('evidencias.store');
    Route::delete('/evidencias/{evidencia}',  [EvidenciaController::class,'destroy'])->name('evidencias.destroy');
});

/* ==========================
 |  CALIDAD / CLIENTE / FACTURAS
 * ========================== */
Route::middleware('auth')->group(function () {
    // Calidad
    Route::get('/ordenes/{orden}/calidad', [CalidadController::class,'show'])
        ->middleware('role:calidad|admin')->name('calidad.show');
    Route::post('/ordenes/{orden}/calidad/validar', [CalidadController::class,'validar'])
        ->middleware('role:calidad|admin')->name('calidad.validar');
    Route::post('/ordenes/{orden}/calidad/rechazar', [CalidadController::class,'rechazar'])
        ->middleware('role:calidad|admin')->name('calidad.rechazar');

    // Cliente
    Route::post('/ordenes/{orden}/cliente/autorizar', [ClienteController::class,'autorizar'])
        ->name('cliente.autorizar');

    // Facturas
    Route::get('/ordenes/{orden}/facturar', [FacturaController::class,'createFromOrden'])
        ->middleware('role:facturacion|admin')->name('facturas.createFromOrden');
    Route::post('/ordenes/{orden}/facturar', [FacturaController::class,'storeFromOrden'])
        ->middleware('role:facturacion|admin')->name('facturas.storeFromOrden');

    Route::get('/facturas/{factura}', [FacturaController::class,'show'])
        ->middleware('role:facturacion|admin')->name('facturas.show');
    Route::get('/facturas/{factura}/pdf',[FacturaController::class,'pdf'])->name('facturas.pdf');

    Route::post('/facturas/{factura}/facturado', [FacturaController::class,'marcarFacturado'])
        ->middleware('role:facturacion|admin')->name('facturas.facturado');
    Route::post('/facturas/{factura}/cobro', [FacturaController::class,'marcarCobro'])
        ->middleware('role:facturacion|admin')->name('facturas.cobro');
    Route::post('/facturas/{factura}/pagado', [FacturaController::class,'marcarPagado'])
        ->middleware('role:facturacion|admin')->name('facturas.pagado');
});

/* ==========
 |  PERFIL
 * ========== */
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Auth (login/registro de Breeze)
require __DIR__.'/auth.php';
