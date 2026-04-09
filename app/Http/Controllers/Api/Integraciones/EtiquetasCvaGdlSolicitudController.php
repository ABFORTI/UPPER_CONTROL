<?php

namespace App\Http\Controllers\Api\Integraciones;

use App\Domain\Servicios\PricingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Integraciones\StoreEtiquetasCvaGdlSolicitudRequest;
use App\Models\Area;
use App\Models\CentroCosto;
use App\Models\CentroTrabajo;
use App\Models\Marca;
use App\Models\ServicioCentro;
use App\Models\ServicioEmpresa;
use App\Models\Solicitud;
use App\Models\SolicitudServicio;
use App\Services\Notifier;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EtiquetasCvaGdlSolicitudController extends Controller
{
    public function __invoke(StoreEtiquetasCvaGdlSolicitudRequest $request): JsonResponse
    {
        Log::info('TEMP DEBUG etiquetas: peticion recibida en endpoint de integracion', [
            'route' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_id' => optional($request->user())->id,
        ]);

        Log::info('TEMP DEBUG etiquetas: payload recibido', [
            'payload' => $request->all(),
        ]);

        try {
            $data = $request->validated();
            $config = $this->integrationConfig();

            $existing = $this->findExistingSolicitud($config['origen_integracion'], $data['referencia_externa']);
            if ($existing) {
                return $this->duplicateResponse($existing);
            }

            $centro = $this->resolveCentroTrabajo($config);
            $centroCosto = $this->resolveCentroCosto($centro, $config);
            $area = $this->resolveArea($centro, $config);
            $marca = $this->resolveMarca($centro, $config);
            $servicios = $this->resolveServiciosFromDetalles($data['detalles_caja']);

            $solicitud = $this->createSolicitudWithRetry(
                $request,
                $data,
                $config,
                $centro,
                $centroCosto,
                $area,
                $marca,
                $servicios
            );

            $this->notifyCentro($solicitud);

            Log::info('TEMP DEBUG etiquetas: solicitud creada correctamente', [
                'solicitud_id' => (int) $solicitud->id,
                'folio' => (string) $solicitud->folio,
                'referencia_externa' => (string) $solicitud->referencia_externa,
            ]);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Solicitud creada correctamente desde integracion.',
                'solicitud_id' => (int) $solicitud->id,
                'folio' => (string) $solicitud->folio,
            ], 201);
        } catch (QueryException $e) {
            if ($this->isSolicitudFolioCollision($e)) {
                Log::warning('TEMP DEBUG etiquetas: colision de folio sin recuperacion', [
                    'message' => $e->getMessage(),
                    'referencia_externa' => $request->input('referencia_externa'),
                ]);

                return response()->json([
                    'ok' => false,
                    'mensaje' => 'Conflicto temporal al generar folio. Reintenta la solicitud.',
                    'detalle' => 'No se pudo reservar un folio unico en este intento.',
                ], 409);
            }

            if ((string) $e->getCode() === '23000') {
                $config = $this->integrationConfig();
                $existing = $this->findExistingSolicitud($config['origen_integracion'], $request->input('referencia_externa'));
                if ($existing) {
                    return $this->duplicateResponse($existing);
                }
            }

            Log::error('Integracion etiquetas CVA GDL: error de base de datos', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'mensaje' => 'No fue posible guardar la solicitud de integracion.',
                'detalle' => $e->getMessage(),
            ], 500);
        } catch (DomainException $e) {
            Log::warning('TEMP DEBUG etiquetas: fallo resolucion de catalogos', [
                'message' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'ok' => false,
                'mensaje' => 'Error de catalogos para integracion.',
                'detalle' => $e->getMessage(),
            ], 422);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'No fue posible generar un folio unico')) {
                Log::warning('TEMP DEBUG etiquetas: reintentos de folio agotados', [
                    'message' => $e->getMessage(),
                    'referencia_externa' => $request->input('referencia_externa'),
                ]);

                return response()->json([
                    'ok' => false,
                    'mensaje' => 'Conflicto temporal al generar folio. Reintenta la solicitud.',
                    'detalle' => 'No fue posible generar un folio unico tras varios intentos.',
                ], 409);
            }

            throw $e;
        } catch (\Throwable $e) {
            Log::error('Integracion etiquetas CVA GDL: error no controlado', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'mensaje' => 'Ocurrio un error interno al procesar la integracion.',
                'detalle' => $e->getMessage(),
            ], 500);
        }
    }

    private function createSolicitudWithRetry(
        StoreEtiquetasCvaGdlSolicitudRequest $request,
        array $data,
        array $config,
        CentroTrabajo $centro,
        CentroCosto $centroCosto,
        Area $area,
        Marca $marca,
        array $servicios
    ): Solicitud {
        $maxAttempts = 5;
        $lastFolioError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return DB::transaction(function () use ($attempt, $request, $data, $config, $centro, $centroCosto, $area, $marca, $servicios) {
                    $pricing = app(PricingService::class);

                    $solicitud = Solicitud::create([
                        'folio' => $this->generateSolicitudFolioForIntegration((int) $centro->id, $attempt - 1),
                        'id_cliente' => (int) $request->user()->id,
                        'id_centrotrabajo' => (int) $centro->id,
                        'id_servicio' => null,
                        'descripcion' => $this->buildSolicitudDescription($data),
                        'id_area' => (int) $area->id,
                        'id_centrocosto' => (int) $centroCosto->id,
                        'id_marca' => (int) $marca->id,
                        'cantidad' => $this->totalPiezas($data['detalles_caja']),
                        'subtotal' => 0,
                        'iva' => 0,
                        'total' => 0,
                        'notas' => $this->buildSolicitudNotes($data),
                        'estatus' => 'pendiente',
                        'origen_integracion' => $config['origen_integracion'],
                        'referencia_externa' => $data['referencia_externa'],
                        'paqueteria' => $data['paqueteria'] ?? null,
                        'numero_factura' => $data['numero_factura'] ?? null,
                        'numero_cajas' => (int) $data['numero_cajas'],
                        'pedido' => $data['pedido'] ?? null,
                        'es_integracion_etiquetas' => true,
                        'solicitante_externo' => $config['solicitante'],
                        'metadata_json' => [
                            'integracion' => [
                                'key' => 'sistema_etiquetas',
                                'site' => 'cva_gdl',
                                'payload_origen' => $data['origen'] ?? null,
                                'payload_sede' => $data['sede'] ?? null,
                            ],
                            'detalles_caja' => array_map(function (array $detalle) {
                                return [
                                    'caja' => (int) $detalle['caja'],
                                    'piezas' => (int) $detalle['piezas'],
                                    'tipo_embalaje' => (int) $detalle['tipo_embalaje'],
                                    'servicio_nombre' => $this->mapTipoEmbalajeToServiceName((int) $detalle['tipo_embalaje']),
                                ];
                            }, $data['detalles_caja']),
                        ],
                    ]);

                    foreach ($servicios as $item) {
                        $tipoCobro = $this->resolveTipoCobro((int) $centro->id, (int) $item['servicio']->id);
                        $precioUnitario = $tipoCobro === 'tamanos'
                            ? 0.0
                            : (float) $pricing->precioUnitario((int) $centro->id, (int) $item['servicio']->id, null);

                        SolicitudServicio::create([
                            'solicitud_id' => (int) $solicitud->id,
                            'servicio_id' => (int) $item['servicio']->id,
                            'descripcion' => 'Caja ' . (int) $item['detalle']['caja'],
                            'tipo_cobro' => $tipoCobro,
                            'cantidad' => (int) $item['detalle']['piezas'],
                            'precio_unitario' => $precioUnitario,
                            'subtotal' => round($precioUnitario * (int) $item['detalle']['piezas'], 2),
                            'service_assignment_status' => 'assigned',
                        ]);
                    }

                    $solicitud->load('servicios');
                    $solicitud->recalcularTotales();

                    return $solicitud->fresh(['centro', 'centroCosto', 'marca', 'area', 'servicios.servicio']);
                });
            } catch (QueryException $e) {
                if (!$this->isSolicitudFolioCollision($e)) {
                    throw $e;
                }

                $lastFolioError = $e;

                Log::warning('TEMP DEBUG etiquetas: colision de folio, reintentando', [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'message' => $e->getMessage(),
                    'referencia_externa' => $data['referencia_externa'] ?? null,
                ]);

                usleep(100000);
            }
        }

        throw new \RuntimeException(
            'No fue posible generar un folio unico tras varios intentos.',
            0,
            $lastFolioError
        );
    }

    private function generateSolicitudFolioForIntegration(int $centroId, int $offset = 0): string
    {
        $centro = CentroTrabajo::find($centroId);
        $prefijo = $centro?->prefijo
            ?? ($centro && $centro->nombre
                ? strtoupper(substr(preg_replace('/[^a-z]/i', '', $centro->nombre), 0, 3))
                : 'UPR');

        $prefijo = strtoupper(substr($prefijo, 0, 10));
        $yyyymm = now()->format('Ym');
        $base = $prefijo . '-' . $yyyymm . '-';

        $maxSeq = (int) Solicitud::query()
            ->where('folio', 'like', $base . '%')
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)), 0) AS max_seq")
            ->lockForUpdate()
            ->value('max_seq');

        $seq = $maxSeq + 1 + max(0, $offset);

        return sprintf('%s%04d', $base, $seq);
    }

    private function isSolicitudFolioCollision(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        if ((string) $e->getCode() !== '23000') {
            return false;
        }

        if (str_contains($message, 'solicitudes_folio_unique')) {
            return true;
        }

        return str_contains($message, 'duplicate entry') && str_contains($message, 'folio');
    }

    private function integrationConfig(): array
    {
        $config = config('integraciones.sistema_etiquetas.cva_gdl');

        if (!is_array($config) || empty($config)) {
            throw new DomainException('No existe configuracion para integraciones.sistema_etiquetas.cva_gdl.');
        }

        return $config;
    }

    private function resolveCentroTrabajo(array $config): CentroTrabajo
    {
        $centro = CentroTrabajo::query()
            ->where('nombre', $config['centro_trabajo'])
            ->where('activo', true)
            ->first();

        if (!$centro) {
            throw new DomainException('No existe el centro de trabajo configurado: ' . $config['centro_trabajo']);
        }

        return $centro;
    }

    private function resolveCentroCosto(CentroTrabajo $centro, array $config): CentroCosto
    {
        $centroCosto = CentroCosto::query()
            ->where('id_centrotrabajo', (int) $centro->id)
            ->where('nombre', $config['centro_costos'])
            ->where('activo', true)
            ->first();

        if (!$centroCosto) {
            throw new DomainException('No existe el centro de costos configurado para ' . $centro->nombre . ': ' . $config['centro_costos']);
        }

        return $centroCosto;
    }

    private function resolveArea(CentroTrabajo $centro, array $config): Area
    {
        $area = Area::query()
            ->where('id_centrotrabajo', (int) $centro->id)
            ->where('nombre', $config['area'])
            ->where('activo', true)
            ->first();

        if (!$area) {
            throw new DomainException('No existe el area configurada para ' . $centro->nombre . ': ' . $config['area']);
        }

        return $area;
    }

    private function resolveMarca(CentroTrabajo $centro, array $config): Marca
    {
        $marca = Marca::query()
            ->where('id_centrotrabajo', (int) $centro->id)
            ->where('nombre', $config['marca'])
            ->where('activo', true)
            ->first();

        if (!$marca) {
            throw new DomainException('No existe la marca configurada para ' . $centro->nombre . ': ' . $config['marca']);
        }

        return $marca;
    }

    private function resolveServiciosFromDetalles(array $detalles): array
    {
        $resolved = [];

        foreach ($detalles as $detalle) {
            $tipoEmbalaje = (int) $detalle['tipo_embalaje'];
            $serviceName = $this->mapTipoEmbalajeToServiceName($tipoEmbalaje);

            $servicio = ServicioEmpresa::query()
                ->where('nombre', $serviceName)
                ->first();

            if (!$servicio) {
                throw new DomainException('No existe el servicio configurado para tipo_embalaje ' . $tipoEmbalaje . ': ' . $serviceName);
            }

            $resolved[] = [
                'detalle' => $detalle,
                'servicio' => $servicio,
            ];
        }

        return $resolved;
    }

    private function mapTipoEmbalajeToServiceName(int $tipoEmbalaje): string
    {
        return match ($tipoEmbalaje) {
            1 => 'EMBALAJE TIPO 1',
            2 => 'EMBALAJE TIPO 2',
            3 => 'EMBALAJE TIPO 3',
            4 => 'EMBALAJE TIPO 4',
            5 => 'EMBALAJE TIPO 5',
            default => throw new DomainException('tipo_embalaje no soportado: ' . $tipoEmbalaje),
        };
    }

    private function resolveTipoCobro(int $centroId, int $servicioId): string
    {
        $usaTamanos = ServicioCentro::query()
            ->where('id_centrotrabajo', $centroId)
            ->where('id_servicio', $servicioId)
            ->whereHas('tamanos')
            ->exists();

        return $usaTamanos ? 'tamanos' : 'cantidad';
    }

    private function buildSolicitudDescription(array $data): string
    {
        $numeroFactura = trim((string) ($data['numero_factura'] ?? ''));

        if ($numeroFactura !== '') {
            return 'Integración etiquetas factura ' . $numeroFactura;
        }

        return 'Integración etiquetas';
    }

    private function buildSolicitudNotes(array $data): string
    {
        $lineas = [
            'Solicitud generada por integracion de sistema de etiquetas.',
            'Referencia externa: ' . trim((string) $data['referencia_externa']),
        ];

        if (!empty($data['paqueteria'])) {
            $lineas[] = 'Paqueteria: ' . trim((string) $data['paqueteria']);
        }
        if (!empty($data['numero_factura'])) {
            $lineas[] = 'Factura: ' . trim((string) $data['numero_factura']);
        }
        if (!empty($data['pedido'])) {
            $lineas[] = 'Pedido: ' . trim((string) $data['pedido']);
        }

        $lineas[] = 'Cajas: ' . (int) $data['numero_cajas'];

        return implode("\n", $lineas);
    }

    private function totalPiezas(array $detalles): int
    {
        return (int) collect($detalles)->sum(fn (array $detalle) => (int) $detalle['piezas']);
    }

    private function findExistingSolicitud(string $origenIntegracion, ?string $referenciaExterna): ?Solicitud
    {
        $referenciaExterna = is_string($referenciaExterna) ? trim($referenciaExterna) : null;
        if (!$referenciaExterna) {
            return null;
        }

        return Solicitud::query()
            ->with(['centro', 'centroCosto', 'marca', 'area'])
            ->where('origen_integracion', $origenIntegracion)
            ->where('referencia_externa', $referenciaExterna)
            ->first();
    }

    private function duplicateResponse(Solicitud $solicitud): JsonResponse
    {
        Log::warning('TEMP DEBUG etiquetas: referencia_externa duplicada', [
            'solicitud_id' => (int) $solicitud->id,
            'folio' => (string) $solicitud->folio,
            'referencia_externa' => (string) $solicitud->referencia_externa,
        ]);

        return response()->json([
            'ok' => false,
            'mensaje' => 'La referencia_externa ya fue procesada previamente.',
            'detalle' => 'Referencia duplicada para este origen de integracion.',
            'solicitud_id' => (int) $solicitud->id,
            'folio' => (string) $solicitud->folio,
        ], 200);
    }

    private function notifyCentro(Solicitud $solicitud): void
    {
        try {
            $serviciosNombres = $solicitud->servicios()->with('servicio')->get()->pluck('servicio.nombre')->filter()->join(', ');

            Notifier::toRoleInCentro(
                'coordinador',
                (int) $solicitud->id_centrotrabajo,
                'Nueva solicitud de integracion',
                "Se recibio la solicitud {$solicitud->folio} desde sistema de etiquetas con servicios: {$serviciosNombres}.",
                route('solicitudes.show', $solicitud->id)
            );
        } catch (\Throwable $e) {
            Log::warning('Integracion etiquetas CVA GDL: error al notificar coordinador', [
                'solicitud_id' => $solicitud->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
