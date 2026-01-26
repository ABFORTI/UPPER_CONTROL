<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuotationResource;
use App\Models\Cotizacion;
use App\Models\Solicitud;
use App\Services\QuotationApprovalService;
use App\Services\QuotationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ClientQuotationController extends Controller
{
    private function tokenFromRequest(Request $req): string
    {
        // Especificación: token en query string (?token=...)
        return $req->query('token', '');
    }

    private function assertValidToken(Request $req, Cotizacion $cotizacion): string
    {
        $token = trim((string)$this->tokenFromRequest($req));
        if ($token === '') {
            abort(response()->json([
                'message' => 'Token requerido.',
                'code' => 'TOKEN_REQUIRED',
            ], 401));
        }

        $svc = app(QuotationService::class);
        if (!$svc->approvalTokenMatches($cotizacion, $token)) {
            abort(response()->json([
                'message' => 'Token inválido.',
                'code' => 'TOKEN_INVALID',
            ], 401));
        }

        return $token;
    }

    private function loadForClient(Cotizacion $cotizacion): Cotizacion
    {
        $cotizacion->load([
            'cliente:id,name,email',
            'centro:id,nombre,prefijo',
            'centroCosto:id,nombre',
            'marca:id,nombre',
            'area:id,nombre',
            'items.servicios.servicio:id,nombre,usa_tamanos',
        ]);

        return $cotizacion;
    }

    private function requireSent(Cotizacion $cotizacion): void
    {
        if ($cotizacion->estatus !== Cotizacion::ESTATUS_SENT) {
            abort(response()->json([
                'message' => 'La cotización ya fue procesada o no está disponible.',
                'code' => 'STATUS_NOT_SENT',
                'status' => $cotizacion->estatus,
            ], 409));
        }
    }

    private function requireNotExpired(Cotizacion $cotizacion): void
    {
        if ($cotizacion->isExpired()) {
            // Opcional: marcarla como expirada para consistencia
            $cotizacion->forceFill([
                'estatus' => Cotizacion::ESTATUS_EXPIRED,
            ])->save();

            abort(response()->json([
                'message' => 'Cotización expirada',
                'code' => 'QUOTATION_EXPIRED',
            ], 410));
        }
    }

    public function show(Request $req, Cotizacion $cotizacion)
    {
        $this->assertValidToken($req, $cotizacion);
        $this->requireSent($cotizacion);

        // En GET solo lectura: si está expirada, reportar
        if ($cotizacion->isExpired()) {
            return response()->json([
                'message' => 'Cotización expirada',
                'code' => 'QUOTATION_EXPIRED',
            ], 410);
        }

        $this->loadForClient($cotizacion);

        return response()->json([
            'data' => (new QuotationResource($cotizacion))->toArray($req),
        ]);
    }

    public function approve(Request $req, Cotizacion $cotizacion)
    {
        $token = $this->assertValidToken($req, $cotizacion);
        $this->requireSent($cotizacion);
        $this->requireNotExpired($cotizacion);

        // Nota: el token plano no se guarda; solo se valida y se invalida el hash.
        // La creación de solicitudes sucede dentro de la transacción vía evento/listener.
        try {
            app(QuotationApprovalService::class)->approveSentQuotation(
                $cotizacion,
                (int)$cotizacion->id_cliente,
                'api_client',
                [
                    'ip' => $req->ip(),
                    'token_present' => $token !== '' ? true : false,
                ]
            );
        } catch (\RuntimeException $e) {
            // En caso de carrera/concurrencia, mantener semánticas del API.
            $cotizacion->refresh();
            if ($cotizacion->isExpired()) {
                return response()->json([
                    'message' => 'Cotización expirada',
                    'code' => 'QUOTATION_EXPIRED',
                ], 410);
            }

            return response()->json([
                'message' => 'La cotización ya fue procesada o no está disponible.',
                'code' => 'STATUS_NOT_SENT',
                'status' => $cotizacion->estatus,
            ], 409);
        }

        $cotizacion->refresh();
        $this->loadForClient($cotizacion);

        $solicitudesCount = 0;
        if (Schema::hasTable('solicitudes')) {
            $solicitudesCount = Solicitud::where('id_cotizacion', (int)$cotizacion->id)->count();
        }

        return response()->json([
            'message' => 'Cotización autorizada.',
            'data' => (new QuotationResource($cotizacion))->toArray($req),
            'solicitudes_generadas' => $solicitudesCount,
        ]);
    }

    public function reject(Request $req, Cotizacion $cotizacion)
    {
        $this->assertValidToken($req, $cotizacion);
        $this->requireSent($cotizacion);
        $this->requireNotExpired($cotizacion);

        $data = $req->validate([
            // Motivo opcional
            'motivo' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $motivo = $data['motivo'] ?? $data['reason'] ?? null;
        $motivo = is_string($motivo) ? trim($motivo) : null;
        if ($motivo === '') $motivo = null;

        try {
            DB::transaction(function () use ($cotizacion, $motivo, $req) {
                $locked = Cotizacion::whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();

                if ($locked->estatus !== Cotizacion::ESTATUS_SENT) {
                    throw ValidationException::withMessages(['status' => 'La cotización ya fue procesada.']);
                }

                if ($locked->isExpired()) {
                    $locked->update(['estatus' => Cotizacion::ESTATUS_EXPIRED]);
                    throw ValidationException::withMessages(['status' => 'Cotización expirada']);
                }

                $locked->update([
                    'estatus' => Cotizacion::ESTATUS_REJECTED,
                    'rejected_at' => now(),
                    'motivo_rechazo' => $motivo,
                    // Buenas prácticas: invalidar el token después del uso
                    'approval_token_hash' => null,
                ]);

                if (Schema::hasTable('cotizacion_audit_logs')) {
                    try {
                        DB::table('cotizacion_audit_logs')->insert([
                            'cotizacion_id' => (int)$locked->id,
                            'action' => 'rejected',
                            'actor_user_id' => null,
                            'actor_client_id' => (int)$locked->id_cliente,
                            'payload' => json_encode([
                                'via' => 'api_client',
                                'ip' => $req->ip(),
                                'motivo' => $motivo,
                            ]),
                            'created_at' => now(),
                        ]);
                    } catch (\Throwable $e) {
                        // noop
                    }
                }
            });
        } catch (ValidationException $e) {
            $statusMsg = (string)($e->errors()['status'][0] ?? '');
            if (stripos($statusMsg, 'expirada') !== false) {
                return response()->json([
                    'message' => 'Cotización expirada',
                    'code' => 'QUOTATION_EXPIRED',
                ], 410);
            }
            throw $e;
        }

        $cotizacion->refresh();
        $this->loadForClient($cotizacion);

        return response()->json([
            'message' => 'Cotización rechazada.',
            'data' => (new QuotationResource($cotizacion))->toArray($req),
        ]);
    }
}
