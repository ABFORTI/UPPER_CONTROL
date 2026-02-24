<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\{
    ProfileController,
    SolicitudController,
    CotizacionController,
    OrdenController,
    OrdenExcelController,
    SolicitudExcelController,
    CalidadController,
    ClienteController,
    FacturaController,
    DashboardController,
    PrecioController,
    EvidenciaController,
    HomeController
};

use App\Http\Controllers\ClientPublicQuotationController;

use App\Http\Controllers\Admin\{
    UserController as AdminUserController,
    ActivityController,
    ImpersonateController,
    CentroController,
    BackupController,
    CentroFeatureController
};

// Home -> Redirige a dashboard (o login si no está autenticado)
// Definir explícitamente GET como primer método
Route::match(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/', [HomeController::class, 'index'])->name('home');

/* ===========================
 |  DASHBOARD & NOTIFICACIONES
 * =========================== */
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/export/ots', [DashboardController::class, 'exportOts'])->name('dashboard.export.ots');
    Route::get('/dashboard/export/facturas', [DashboardController::class, 'exportFacturas'])->name('dashboard.export.facturas');

    Route::get('/notificaciones', [\App\Http\Controllers\SupportController::class, 'notificacionesIndex'])
        ->name('notificaciones.index');

    Route::post('/notificaciones/read-all', [\App\Http\Controllers\SupportController::class, 'notificacionesReadAll'])
        ->name('notificaciones.read_all');

    Route::post('/notificaciones/{notification}/read', [\App\Http\Controllers\SupportController::class, 'notificacionesRead'])
        ->name('notificaciones.read');
});

/* =====================
 |  CLIENTE (público)
 * =====================
 | Vista pública para revisar/autorizar/rechazar con token.
 | La validación de token/estatus se realiza en /api/client/*.
 */

Route::get('/client/public/quotations/{cotizacion}', ClientPublicQuotationController::class)
    ->middleware(['throttle:30,1'])
    ->name('client.public.quotations.show');

/* ==========
 |  SERVICIOS (precios)
 * ========== */
Route::middleware(['auth', 'check.servicios'])->group(function () {
    Route::get('/servicios', [PrecioController::class,'index'])->name('servicios.index');
    Route::get('/servicios/create', [PrecioController::class,'create'])->name('servicios.create');
    Route::post('/servicios/guardar', [PrecioController::class,'guardar'])->name('servicios.guardar');
    Route::post('/servicios/crear', [PrecioController::class,'crear'])->name('servicios.crear');
    Route::post('/servicios/clonar', [PrecioController::class,'clonar'])->name('servicios.clonar');
    Route::post('/servicios/eliminar', [PrecioController::class,'eliminar'])->name('servicios.eliminar');
});

/* ======================
 |  CENTROS DE COSTOS
 * ====================== */
Route::middleware(['auth', 'check.areas'])->group(function () {
    Route::get('/centros-costos', [\App\Http\Controllers\CentroCostoController::class,'index'])->name('centros_costos.index');
    Route::post('/centros-costos', [\App\Http\Controllers\CentroCostoController::class,'store'])->name('centros_costos.store');
    Route::put('/centros-costos/{centroCosto}', [\App\Http\Controllers\CentroCostoController::class,'update'])->name('centros_costos.update');
    Route::delete('/centros-costos/{centroCosto}', [\App\Http\Controllers\CentroCostoController::class,'destroy'])->name('centros_costos.destroy');
});

/* ==========
 |  MARCAS
 * ========== */
Route::middleware(['auth', 'check.areas'])->group(function () {
    Route::get('/marcas', [\App\Http\Controllers\MarcaController::class,'index'])->name('marcas.index');
    Route::post('/marcas', [\App\Http\Controllers\MarcaController::class,'store'])->name('marcas.store');
    Route::put('/marcas/{marca}', [\App\Http\Controllers\MarcaController::class,'update'])->name('marcas.update');
    Route::delete('/marcas/{marca}', [\App\Http\Controllers\MarcaController::class,'destroy'])->name('marcas.destroy');
});

/* ==========
 |  ÁREAS
 * ========== */
Route::middleware(['auth', 'check.areas'])->group(function () {
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

    // Excel: subir, guardar y parsear (sesión web)
    Route::post('/solicitudes/parse-excel', [SolicitudExcelController::class, 'parseExcel'])
        ->middleware('feature:subir_excel')
        ->name('solicitudes.parse-excel');
    Route::get('/solicitudes/excel/{archivo}', [SolicitudExcelController::class, 'download'])
        ->middleware('feature:subir_excel')
        ->where('archivo', '[A-Za-z0-9._-]+')
        ->name('solicitudes.excel.download');

    // Excel origen (ya guardado) asociado a la Solicitud
    Route::get('/solicitudes/{solicitud}/excel-origen', [SolicitudExcelController::class, 'downloadBySolicitud'])
        ->middleware('feature:subir_excel')
        ->name('solicitudes.excel.origen');

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
 |  COTIZACIONES
 * =============== */
Route::middleware(['auth', 'feature:ver_cotizacion'])->group(function () {
    Route::get('/cotizaciones', [CotizacionController::class, 'index'])->name('cotizaciones.index');
    Route::get('/cotizaciones/create', [CotizacionController::class, 'create'])
        ->middleware('role:coordinador|admin')
        ->name('cotizaciones.create');
    Route::post('/cotizaciones', [CotizacionController::class, 'store'])
        ->middleware('role:coordinador|admin')
        ->name('cotizaciones.store');

    Route::get('/cotizaciones/{cotizacion}/edit', [CotizacionController::class, 'edit'])
        ->middleware('role:coordinador|admin')
        ->name('cotizaciones.edit');
    Route::patch('/cotizaciones/{cotizacion}', [CotizacionController::class, 'update'])
        ->middleware('role:coordinador|admin')
        ->name('cotizaciones.update');
    Route::post('/cotizaciones/{cotizacion}/duplicate', [CotizacionController::class, 'duplicate'])
        ->middleware('role:coordinador|admin')
        ->name('cotizaciones.duplicate');
    Route::get('/cotizaciones/{cotizacion}', [CotizacionController::class, 'show'])->name('cotizaciones.show');

    // Enviar / cancelar (coordinador/admin)
    Route::get('/cotizaciones/{cotizacion}/recipients', [CotizacionController::class, 'recipients'])
        ->middleware('role:coordinador|admin')
        ->name('cotizaciones.recipients');
    Route::post('/cotizaciones/{cotizacion}/send', [CotizacionController::class, 'send'])
        ->middleware('role:coordinador|admin')
        ->name('cotizaciones.send');

    // PDF (web) - protegido por auth + policy view
    Route::get('/cotizaciones/{cotizacion}/pdf', [CotizacionController::class, 'pdf'])
        ->name('cotizaciones.pdf');
    Route::post('/cotizaciones/{cotizacion}/cancel', [CotizacionController::class, 'cancel'])
        ->middleware('role:coordinador|admin')
        ->name('cotizaciones.cancel');

    // Revisión por cliente con link firmado (redirige a login si no está autenticado)
    Route::get('/cotizaciones/{cotizacion}/review', [CotizacionController::class, 'review'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('cotizaciones.review');

    // Revisión por cliente desde el sistema (campanita / email con token)
    Route::get('/client/quotations/{cotizacion}', [CotizacionController::class, 'review'])
        ->middleware(['throttle:12,1'])
        ->name('client.quotations.review');

    // Respuesta del cliente (auth + policy)
    Route::post('/cotizaciones/{cotizacion}/approve', [CotizacionController::class, 'approve'])
        ->name('cotizaciones.approve');
    Route::post('/cotizaciones/{cotizacion}/reject', [CotizacionController::class, 'reject'])
        ->name('cotizaciones.reject');
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
    Route::get('/ordenes/export', [OrdenController::class,'export'])->name('ordenes.export');
    Route::get('/ordenes/export-facturacion', [OrdenController::class,'exportFacturacion'])
        ->middleware('role:admin|facturacion|gerente_upper')
        ->name('ordenes.exportFacturacion');
    Route::get('/ordenes/{orden}', [OrdenController::class,'show'])->name('ordenes.show');

    Route::patch('/ordenes/{orden}/asignar-tl', [OrdenController::class,'asignarTL'])->name('ordenes.asignarTL');
    Route::post('/ordenes/{orden}/avances', [OrdenController::class,'registrarAvance'])->name('ordenes.avances.store');
    Route::post('/ordenes/{orden}/faltantes', [OrdenController::class,'registrarFaltantes'])->name('ordenes.faltantes.store');
    Route::patch('/ordenes/{orden}/segmentos/{segmento}', [OrdenController::class,'updateSegmentoProduccion'])->name('ordenes.segmentos.update');
    
    // Agregar servicio adicional (solo admin/coordinador/TL)
    Route::post('/ordenes/{orden}/servicios-adicionales', [OrdenController::class,'agregarServicioAdicional'])
        ->middleware('role:admin|coordinador|team_leader')
        ->name('ordenes.agregarServicioAdicional');

    // Definir desglose por tamaños (flujo diferido)
    Route::post('/ordenes/{orden}/tamanos/definir', [OrdenController::class,'definirTamanos'])->name('ordenes.definirTamanos');
    Route::post('/ordenes/{orden}/servicios/{servicio}/tamanos/definir', [OrdenController::class,'definirTamanosServicio'])->name('ordenes.servicios.definirTamanos');


    // PDFs
    Route::get('/ordenes/{orden}/pdf',  [OrdenController::class,'pdf'])->name('ordenes.pdf');

    // Archivo Excel: upload y download
    Route::post('/ordenes/{orden}/archivo', [OrdenExcelController::class,'upload'])->name('ordenes.archivo.upload');
    Route::get('/ordenes/{orden}/archivo', [OrdenExcelController::class,'download'])->name('ordenes.archivo.download');

    // Excel origen (el que se usó para precargar la solicitud)
    Route::get('/ordenes/{orden}/excel-origen', [SolicitudExcelController::class, 'downloadOrigenFromOrden'])
        ->middleware('feature:subir_excel')
        ->name('ordenes.excel.origen');

    // Evidencias
    Route::post('/ordenes/{orden}/evidencias', [EvidenciaController::class,'store'])->name('evidencias.store');
    Route::delete('/evidencias/{evidencia}',  [EvidenciaController::class,'destroy'])->name('evidencias.destroy');
});

/* =============================
 |  OT CON MÚLTIPLES SERVICIOS
 * ============================= */
Route::middleware('auth')->group(function () {
    Route::get('/ot-multi-servicio/create', [\App\Http\Controllers\OTMultiServicioController::class, 'create'])
        ->name('ot-multi-servicio.create');
    Route::post('/ot-multi-servicio', [\App\Http\Controllers\OTMultiServicioController::class, 'store'])
        ->name('ot-multi-servicio.store');
    Route::get('/ot-multi-servicio/{orden}', [\App\Http\Controllers\OTMultiServicioController::class, 'show'])
        ->name('ot-multi-servicio.show');
    Route::post('/ot-multi-servicio/{orden}/servicios/{servicio}/faltantes', [\App\Http\Controllers\OTMultiServicioController::class, 'registrarFaltantesServicio'])
        ->name('ot-multi-servicio.servicios.faltantes');
});

/* ===========================
 |  CORTES DE OT (SPLIT)
 * =========================== */
Route::middleware('auth')->group(function () {
    Route::get('/ots/{ot}/cortes', [\App\Http\Controllers\OtCorteController::class, 'index'])
        ->name('ot-cortes.index');
    Route::post('/ots/{ot}/cortes/preview', [\App\Http\Controllers\OtCorteController::class, 'preview'])
        ->middleware('role:admin|coordinador|team_leader|facturacion')
        ->name('ot-cortes.preview');
    Route::post('/ots/{ot}/cortes', [\App\Http\Controllers\OtCorteController::class, 'store'])
        ->middleware('role:admin|coordinador|team_leader|facturacion')
        ->name('ot-cortes.store');
    Route::get('/cortes/{corte}', [\App\Http\Controllers\OtCorteController::class, 'show'])
        ->name('ot-cortes.show');
    Route::patch('/cortes/{corte}/estatus', [\App\Http\Controllers\OtCorteController::class, 'updateEstatus'])
        ->middleware('role:admin|coordinador|team_leader|facturacion')
        ->name('ot-cortes.updateEstatus');
});

/* ==========================
 |  CALIDAD / CLIENTE / FACTURAS
 * ========================== */
Route::middleware('auth')->group(function () {
    // Calidad
    // Gerente Upper puede ver (solo lectura) las pantallas de calidad
    Route::get('/calidad', [CalidadController::class,'index'])
        ->middleware('role:calidad|admin|gerente_upper')->name('calidad.index');
    Route::get('/ordenes/{orden}/calidad', [CalidadController::class,'show'])
        ->middleware('role:calidad|admin|gerente_upper')->name('calidad.show');
    Route::post('/ordenes/{orden}/calidad/validar', [CalidadController::class,'validar'])
        ->middleware('role:calidad|admin')->name('calidad.validar');
    Route::post('/ordenes/{orden}/calidad/rechazar', [CalidadController::class,'rechazar'])
        ->middleware('role:calidad|admin')->name('calidad.rechazar');

    // Supervisor (antes 'cliente')
    Route::post('/ordenes/{orden}/cliente/autorizar', [ClienteController::class,'autorizar'])
        ->name('cliente.autorizar');

    // Facturas - Supervisor (antes 'cliente') puede ver el listado de sus facturas
    // Gerente Upper puede ver listados/detalles de facturas (solo lectura)
    Route::get('/facturas', [FacturaController::class,'index'])
        ->middleware('role:facturacion|admin|Cliente_Supervisor|gerente_upper|Cliente_Gerente')->name('facturas.index');
    Route::get('/ordenes/{orden}/facturar', [FacturaController::class,'createFromOrden'])
        ->middleware('role:facturacion|admin')->name('facturas.createFromOrden');
    Route::post('/ordenes/{orden}/facturar', [FacturaController::class,'storeFromOrden'])
        ->middleware('role:facturacion|admin')->name('facturas.storeFromOrden');

    // Supervisor (antes 'cliente') puede ver factura y generar PDF
    Route::get('/facturas/{factura}', [FacturaController::class,'show'])
        ->middleware('role:facturacion|admin|Cliente_Supervisor|gerente_upper|Cliente_Gerente')->name('facturas.show');
    Route::get('/facturas/{factura}/pdf',[FacturaController::class,'pdf'])
        ->middleware('role:facturacion|admin|Cliente_Supervisor|gerente_upper|Cliente_Gerente')->name('facturas.pdf');

    // Solo facturacion y admin pueden ejecutar estas acciones
    Route::post('/facturas/{factura}/facturado', [FacturaController::class,'marcarFacturado'])
        ->middleware('role:facturacion|admin')->name('facturas.facturado');
    Route::post('/facturas/{factura}/xml', [FacturaController::class,'uploadXml'])
        ->middleware('role:facturacion|admin')->name('facturas.xml');
    Route::post('/facturas/{factura}/cobro', [FacturaController::class,'marcarCobro'])
        ->middleware('role:facturacion|admin')->name('facturas.cobro');
    Route::post('/facturas/{factura}/pagado', [FacturaController::class,'marcarPagado'])
        ->middleware('role:facturacion|admin')->name('facturas.pagado');

    // Facturación múltiple (agrupar varias OTs en una sola factura)
    Route::post('/facturas/batch', [FacturaController::class,'storeBatch'])
        ->middleware('role:facturacion|admin')->name('facturas.batch');
    Route::get('/facturas/batch/create', [FacturaController::class,'createBatch'])
        ->middleware('role:facturacion|admin')->name('facturas.batch.create');
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

    // Funcionalidades por centro
    Route::get('/centros/features', [CentroFeatureController::class, 'index'])->name('centros.features.index');
    Route::put('/centros/{centro}/features', [CentroFeatureController::class, 'update'])->name('centros.features.update');
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

/* ======================================
 |  Fallback para servir archivos públicos
 * ======================================
  Si el symlink public/storage NO existe (hosting compartido, restricción), se evitarán 404
  al abrir evidencias y demás archivos almacenados en el disco `public`. Sólo usuarios autenticados.
  Nota: El path recibido puede venir como 'evidencias/...'
      o con prefijo 'app/public/evidencias/...'; se normaliza al root del disco.
*/
Route::middleware('auth')->get('/secure-files/{path}', [\App\Http\Controllers\SupportController::class, 'storage'])
    ->where('path','.*')
    ->name('storage.serve');

// TEMPORAL: Debug de roles
Route::middleware('auth')->get('/test-roles-debug', [\App\Http\Controllers\SupportController::class, 'testRolesDebug']);
