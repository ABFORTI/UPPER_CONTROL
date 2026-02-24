<?php

namespace App\Http\Controllers;

use App\Http\Requests\OtCortePreviewRequest;
use App\Http\Requests\OtCorteStoreRequest;
use App\Models\Orden;
use App\Models\OtCorte;
use App\Services\Notifier;
use App\Services\OtSplitService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OtCorteController extends Controller
{
    public function __construct(
        protected OtSplitService $splitService
    ) {}

    /**
     * POST /ots/{ot}/cortes/preview
     *
     * Devuelve la preview de lo que se cortaría por concepto.
     */
    public function preview(OtCortePreviewRequest $request, Orden $ot): JsonResponse
    {
        $conceptos = $this->splitService->preview(
            $ot,
            $request->input('periodo_inicio'),
            $request->input('periodo_fin'),
        );

        return response()->json([
            'ot_id'     => $ot->id,
            'conceptos' => $conceptos,
        ]);
    }

    /**
     * POST /ots/{ot}/cortes
     *
     * Crea el corte y opcionalmente la OT hija con remanente.
     */
    public function store(OtCorteStoreRequest $request, Orden $ot): JsonResponse
    {
        try {
            $corte = $this->splitService->crearCorte(
                $ot,
                $request->validated(),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => "Corte {$corte->folio_corte} creado exitosamente.",
                'corte'   => $this->splitService->getCorteDetalle($corte),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /cortes/{corte}
     *
     * Muestra el detalle de un corte.
     */
    public function show(OtCorte $corte): JsonResponse
    {
        return response()->json(
            $this->splitService->getCorteDetalle($corte)
        );
    }

    /**
     * GET /ots/{ot}/cortes
     *
     * Lista todos los cortes de una OT.
     */
    public function index(Orden $ot): JsonResponse
    {
        $cortes = $ot->cortes()
            ->with(['detalles.otServicio.servicio', 'createdBy', 'otHija'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OtCorte $c) => $this->splitService->getCorteDetalle($c));

        return response()->json([
            'ot_id'  => $ot->id,
            'cortes' => $cortes,
        ]);
    }

    /**
     * PATCH /cortes/{corte}/estatus
     *
     * Cambia el estatus de un corte (draft -> ready_to_bill, o void).
     */
    public function updateEstatus(OtCorte $corte): JsonResponse
    {
        $nuevoEstatus = request('estatus');
        $transiciones = [
            'draft'         => ['ready_to_bill', 'void'],
            'ready_to_bill' => ['void'],
            'billed'        => [],
            'void'          => [],
        ];

        $permitidos = $transiciones[$corte->estatus] ?? [];
        if (! in_array($nuevoEstatus, $permitidos)) {
            return response()->json([
                'success' => false,
                'message' => "No se puede cambiar de '{$corte->estatus}' a '{$nuevoEstatus}'.",
            ], 422);
        }

        $corte->update(['estatus' => $nuevoEstatus]);

        // Notificar cuando un corte se anula
        if ($nuevoEstatus === 'void') {
            $ot = $corte->ot;
            if ($ot) {
                Notifier::toRoleInCentro('coordinador', $ot->id_centrotrabajo,
                    'Corte Anulado',
                    "El corte {$corte->folio_corte} de OT #{$ot->id} fue anulado.",
                    route('ordenes.show', $ot->id)
                );
                Notifier::toRoleInCentro('facturacion', $ot->id_centrotrabajo,
                    'Corte Anulado',
                    "El corte {$corte->folio_corte} de OT #{$ot->id} fue anulado. Ya no procede para facturación.",
                    route('ordenes.show', $ot->id)
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Corte actualizado a '{$nuevoEstatus}'.",
            'corte'   => $this->splitService->getCorteDetalle($corte),
        ]);
    }
}
