<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\QuotationItemController;
use App\Http\Controllers\Api\QuotationItemServiceController;
use App\Http\Controllers\Api\ClientQuotationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Protegidas por Sanctum.
| En este módulo, solo coordinador/admin puede administrar cotizaciones.
|
*/

Route::middleware(['auth:sanctum', 'role:coordinador|admin'])->group(function () {
    // Quotations
    Route::get('/quotations', [QuotationController::class, 'index']);
    Route::post('/quotations', [QuotationController::class, 'store']);
    Route::get('/quotations/{cotizacion}', [QuotationController::class, 'show']);
    Route::get('/quotations/{cotizacion}/pdf', [QuotationController::class, 'pdf'])->name('api.quotations.pdf');
    Route::match(['PUT', 'PATCH'], '/quotations/{cotizacion}', [QuotationController::class, 'update']);

    Route::post('/quotations/{cotizacion}/items', [QuotationController::class, 'storeItem']);
    Route::post('/quotations/{cotizacion}/send', [QuotationController::class, 'send']);

    // Quotation items
    Route::match(['PUT', 'PATCH'], '/quotation-items/{cotizacionItem}', [QuotationItemController::class, 'update']);
    Route::delete('/quotation-items/{cotizacionItem}', [QuotationItemController::class, 'destroy']);

    // Quotation item services
    Route::post('/quotation-items/{cotizacionItem}/services', [QuotationItemServiceController::class, 'store']);
    Route::match(['PUT', 'PATCH'], '/quotation-item-services/{cotizacionItemServicio}', [QuotationItemServiceController::class, 'update']);
    Route::delete('/quotation-item-services/{cotizacionItemServicio}', [QuotationItemServiceController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Client Quotation Routes
|--------------------------------------------------------------------------
|
| Flujo público para clientes (sin auth): token en query string.
| - GET /api/client/quotations/{id}?token=...
| - POST /api/client/quotations/{id}/approve?token=...
| - POST /api/client/quotations/{id}/reject?token=...
|
*/

Route::prefix('client')->group(function () {
    // Endpoints públicos con token: protección básica contra fuerza bruta y abuso.
    Route::get('/quotations/{cotizacion}', [ClientQuotationController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('api.client.quotations.show');
    Route::post('/quotations/{cotizacion}/approve', [ClientQuotationController::class, 'approve'])
        ->middleware('throttle:20,1')
        ->name('api.client.quotations.approve');
    Route::post('/quotations/{cotizacion}/reject', [ClientQuotationController::class, 'reject'])
        ->middleware('throttle:20,1')
        ->name('api.client.quotations.reject');
});
