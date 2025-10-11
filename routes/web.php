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

use App\Http\Controllers\Admin\{
    UserController as AdminUserController,
    ActivityController,
    ImpersonateController,
    CentroController,
    BackupController
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

    Route::post('/notificaciones/{notification}/read', function ($notificationId) {
        $user = request()->user();
        $notification = $user->notifications()->whereKey($notificationId)->firstOrFail();
        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }
        // Devolvemos 204 para que el front pueda continuar con la navegación deseada
        return response()->noContent();
    })->name('notificaciones.read');
});

/* ==========
 |  SERVICIOS (precios)
 * ========== */
Route::middleware(['auth','role:admin|coordinador'])->group(function () {
    Route::get('/servicios', [PrecioController::class,'index'])->name('servicios.index');
    Route::get('/servicios/create', [PrecioController::class,'create'])->name('servicios.create');
    Route::post('/servicios/guardar', [PrecioController::class,'guardar'])->name('servicios.guardar');
    Route::post('/servicios/crear', [PrecioController::class,'crear'])->name('servicios.crear');
    Route::post('/servicios/clonar', [PrecioController::class,'clonar'])->name('servicios.clonar');
    Route::post('/servicios/eliminar', [PrecioController::class,'eliminar'])->name('servicios.eliminar');
});

/* ==========
 |  ÁREAS
 * ========== */
Route::middleware(['auth','role:admin|coordinador'])->group(function () {
    Route::get('/areas', [\App\Http\Controllers\AreaController::class,'index'])->name('areas.index');
    Route::post('/areas', [\App\Http\Controllers\AreaController::class,'store'])->name('areas.store');
    Route::put('/areas/{area}', [\App\Http\Controllers\AreaController::class,'update'])->name('areas.update');
    Route::delete('/areas/{area}', [\App\Http\Controllers\AreaController::class,'destroy'])->name('areas.destroy');
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

/* ===============
 |  ARCHIVOS
 * =============== */
Route::middleware('auth')->group(function () {
    Route::get('/archivos/{archivo}/download', [\App\Http\Controllers\ArchivoController::class,'download'])->name('archivos.download');
    Route::get('/archivos/{archivo}/view', [\App\Http\Controllers\ArchivoController::class,'view'])->name('archivos.view');
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
    Route::get('/calidad', [CalidadController::class,'index'])
        ->middleware('role:calidad|admin')->name('calidad.index');
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
    Route::get('/facturas', [FacturaController::class,'index'])
        ->middleware('role:facturacion|admin')->name('facturas.index');
    Route::get('/ordenes/{orden}/facturar', [FacturaController::class,'createFromOrden'])
        ->middleware('role:facturacion|admin')->name('facturas.createFromOrden');
    Route::post('/ordenes/{orden}/facturar', [FacturaController::class,'storeFromOrden'])
        ->middleware('role:facturacion|admin')->name('facturas.storeFromOrden');

    Route::get('/facturas/{factura}', [FacturaController::class,'show'])
        ->middleware('role:facturacion|admin')->name('facturas.show');
    Route::get('/facturas/{factura}/pdf',[FacturaController::class,'pdf'])->name('facturas.pdf');

    Route::post('/facturas/{factura}/facturado', [FacturaController::class,'marcarFacturado'])
        ->middleware('role:facturacion|admin')->name('facturas.facturado');
    Route::post('/facturas/{factura}/xml', [FacturaController::class,'uploadXml'])
        ->middleware('role:facturacion|admin')->name('facturas.xml');
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

/* ==========
 |  ADMINISTRACIÓN
 * ========== */
Route::middleware(['auth','role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users',               [AdminUserController::class,'index'])->name('users.index');
    Route::get('/users/create',        [AdminUserController::class,'create'])->name('users.create');
    Route::post('/users',              [AdminUserController::class,'store'])->name('users.store');
    Route::get('/users/{user}/edit',   [AdminUserController::class,'edit'])->name('users.edit');
    Route::patch('/users/{user}',      [AdminUserController::class,'update'])->name('users.update');
    Route::post('/users/{user}/toggle', [AdminUserController::class,'toggleActive'])->name('users.toggle');
    Route::post('/users/{user}/reset-password', [AdminUserController::class,'resetPassword'])->name('users.reset');
    Route::get('/activity', [ActivityController::class,'index'])->name('activity.index');
    Route::get('/activity/export', [ActivityController::class,'export'])->name('activity.export');

    Route::get('/centros',            [CentroController::class,'index'])->name('centros.index');
    Route::get('/centros/create',     [CentroController::class,'create'])->name('centros.create');
    Route::post('/centros',           [CentroController::class,'store'])->name('centros.store');
    Route::get('/centros/{centro}/edit', [CentroController::class,'edit'])->name('centros.edit');
    Route::patch('/centros/{centro}', [CentroController::class,'update'])->name('centros.update');
    Route::post('/centros/{centro}/toggle', [CentroController::class,'toggle'])->name('centros.toggle');

    // Backups
    Route::get('/backups',        [BackupController::class,'index'])->name('backups.index');
    Route::get('/backups/download', [BackupController::class,'download'])->name('backups.download');
    Route::post('/backups/run',   [BackupController::class,'run'])->name('backups.run'); // manual
});

// Arranque de impersonación (solo admin)
Route::middleware(['auth','role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/users/{user}/impersonate', [ImpersonateController::class,'start'])->name('users.impersonate');
});

// Salir de impersonación (cualquier usuario autenticado que esté impersonando)
Route::post('/admin/impersonate/leave', [ImpersonateController::class,'leave'])
    ->middleware('auth')
    ->name('admin.impersonate.leave');

// Auth (login/registro de Breeze)
require __DIR__.'/auth.php';
