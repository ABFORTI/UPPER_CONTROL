<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientPublicQuotationController extends Controller
{
    public function __invoke(Request $request, Cotizacion $cotizacion)
    {
        // Página pública: la API valida token y estatus.
        return Inertia::render('Client/Quotation', [
            'quotationId' => (int)$cotizacion->id,
            'token' => $request->query('token'),
        ]);
    }
}
