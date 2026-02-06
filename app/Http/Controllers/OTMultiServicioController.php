<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOTRequest;
use App\Models\Orden;
use App\Models\OTServicio;
use App\Models\OTServicioItem;
use App\Models\Solicitud;
use App\Models\ServicioEmpresa;
use App\Models\CentroTrabajo;
use App\Models\Area;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Spatie\Activitylog\Facades\LogBatch;

class OTMultiServicioController extends Controller
{
    /**
     * Mostrar formulario para crear OT con múltiples servicios
     */
    public function create()
    {
        $user = Auth::user();
        
        // Obtener centros de trabajo según permisos
        $canChooseCentro = $user && $user->hasAnyRole(['admin', 'facturacion', 'calidad', 'control', 'comercial']);
        
        if ($canChooseCentro) {
            $centros = CentroTrabajo::where('activo', true)->orderBy('nombre')->get();
        } else {
            $centroId = $user->centro_trabajo_id ?? null;
            $centros = $centroId ? CentroTrabajo::where('id', $centroId)->get() : collect();
        }

        // Obtener servicios
        $servicios = ServicioEmpresa::select('id', 'nombre')->orderBy('nombre')->get();

        // Obtener team leaders
        $teamLeaders = User::role('team_leader')
            ->where('activo', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        // Obtener clientes
        $clientes = User::role('cliente')
            ->where('activo', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('OTMultiServicio/Create', [
            'centros' => $centros,
            'servicios' => $servicios,
            'teamLeaders' => $teamLeaders,
            'clientes' => $clientes,
            'canChooseCentro' => $canChooseCentro,
            'defaultCentroId' => $user->centro_trabajo_id ?? null,
        ]);
    }

    /**
     * Guardar OT con múltiples servicios
     */
    public function store(CreateOTRequest $request)
    {
        $validated = $request->validated();
        $header = $validated['header'];
        $servicios = $validated['servicios'];

        try {
            $orden = DB::transaction(function () use ($header, $servicios) {
                // 1. Crear la Orden de Trabajo
                $orden = Orden::create([
                    'id_solicitud' => null, // OT directa sin solicitud previa
                    'id_centrotrabajo' => $header['centro_trabajo_id'],
                    'id_servicio' => $servicios[0]['servicio_id'], // Primer servicio como referencia legacy
                    'id_area' => $header['area_id'] ?? null,
                    'team_leader_id' => $header['team_leader_id'] ?? null,
                    'descripcion_general' => $header['descripcion_producto'],
                    'estatus' => 'generada',
                    'calidad_resultado' => null,
                    'total_planeado' => 0,
                    'total_real' => 0,
                    'subtotal' => 0,
                    'iva' => 0,
                    'total' => 0,
                ]);

                $subtotalOT = 0;

                // 2. Crear cada servicio con sus items
                foreach ($servicios as $servicioData) {
                    // Crear el servicio
                    $otServicio = OTServicio::create([
                        'ot_id' => $orden->id,
                        'servicio_id' => $servicioData['servicio_id'],
                        'tipo_cobro' => $servicioData['tipo_cobro'],
                        'cantidad' => $servicioData['cantidad'],
                        'precio_unitario' => $servicioData['precio_unitario'],
                        'subtotal' => $servicioData['cantidad'] * $servicioData['precio_unitario'],
                    ]);

                    $subtotalOT += $otServicio->subtotal;

                    // Crear item inicial automáticamente
                    OTServicioItem::create([
                        'ot_servicio_id' => $otServicio->id,
                        'descripcion_item' => $header['descripcion_producto'],
                        'planeado' => $servicioData['cantidad'],
                        'completado' => 0,
                    ]);
                }

                // 3. Calcular y guardar totales de la OT
                $iva = round($subtotalOT * 0.16, 2);
                $total = round($subtotalOT + $iva, 2);

                $orden->update([
                    'subtotal' => $subtotalOT,
                    'iva' => $iva,
                    'total' => $total,
                    'total_planeado' => $total,
                ]);

                // 4. Log de actividad
                activity()
                    ->performedOn($orden)
                    ->causedBy(Auth::user())
                    ->event('created')
                    ->withProperties([
                        'centro_trabajo_id' => $header['centro_trabajo_id'],
                        'servicios_count' => count($servicios),
                        'total' => $total,
                    ])
                    ->log("OT #{$orden->id} creada con múltiples servicios");

                return $orden;
            });

            // Notificar al Team Leader si fue asignado
            if ($orden->team_leader_id) {
                $teamLeader = User::find($orden->team_leader_id);
                if ($teamLeader) {
                    try {
                        $teamLeader->notify(new \App\Notifications\OtAsignada($orden));
                    } catch (\Throwable $e) {
                        Log::warning('Error al notificar TL al crear OT', [
                            'orden_id' => $orden->id,
                            'tl_id' => $teamLeader->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return redirect()->route('ot-multi-servicio.show', $orden->id)
                ->with('success', 'Orden de Trabajo creada exitosamente con ' . count($servicios) . ' servicio(s).');

        } catch (\Exception $e) {
            Log::error('Error al crear OT con múltiples servicios', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Error al crear la orden de trabajo: ' . $e->getMessage()]);
        }
    }

    /**
     * Mostrar detalle de OT con múltiples servicios
     */
    public function show(Orden $orden)
    {
        // Cargar relaciones necesarias
        $orden->load([
            'otServicios.servicio',
            'otServicios.items',
            'otServicios.avances.createdBy',
            'centro',
            'area',
            'teamLeader',
        ]);

        // Preparar datos de servicios con totales calculados
        $serviciosData = $orden->otServicios->map(function ($otServicio) {
            $totales = $otServicio->calcularTotales();
            
            return [
                'id' => $otServicio->id,
                'servicio' => $otServicio->servicio->only(['id', 'nombre']),
                'tipo_cobro' => $otServicio->tipo_cobro,
                'cantidad' => $otServicio->cantidad,
                'precio_unitario' => $otServicio->precio_unitario,
                'subtotal' => $otServicio->subtotal,
                'planeado' => $totales['planeado'],
                'completado' => $totales['completado'],
                'faltantes_registrados' => $totales['faltantes_registrados'],
                'pendiente' => $totales['pendiente'],
                'progreso' => $totales['progreso'],
                'total' => $totales['total'],
                'items' => $otServicio->items->map(function ($item) {
                    // Calcular valores individuales de cada item
                    $planeado = (int)$item->planeado;
                    $completado = (int)$item->completado;
                    $faltantesRegistrados = (int)$item->faltante;
                    $pendiente = max(0, $planeado - ($completado + $faltantesRegistrados));
                    $progreso = $planeado > 0 
                        ? round((($completado + $faltantesRegistrados) / $planeado) * 100) 
                        : 0;
                    
                    return [
                        'id' => $item->id,
                        'descripcion_item' => $item->descripcion_item,
                        'planeado' => $planeado,
                        'completado' => $completado,
                        'faltantes_registrados' => $faltantesRegistrados,
                        'pendiente' => $pendiente,
                        'progreso' => $progreso,
                    ];
                }),
                'avances' => $otServicio->avances->map(function ($avance) {
                    return [
                        'id' => $avance->id,
                        'tarifa' => $avance->tarifa,
                        'precio_unitario_aplicado' => $avance->precio_unitario_aplicado,
                        'cantidad_registrada' => $avance->cantidad_registrada,
                        'comentario' => $avance->comentario,
                        'created_by' => $avance->createdBy->name ?? 'N/A',
                        'created_at' => $avance->created_at->format('Y-m-d H:i'),
                    ];
                }),
            ];
        });
        
        // Verificar si TODOS los servicios tienen progreso = 100%
        $todosServiciosCompletos = $serviciosData->every(function ($servicio) {
            return $servicio['progreso'] >= 100;
        });
        
        // Si todos los servicios están completos Y la orden NO ha avanzado más allá de 'completada', actualizarla
        // No sobrescribir estatus más avanzados: autorizada_cliente, facturada, etc.
        $estatusQueNoSobrescribir = ['autorizada_cliente', 'facturada', 'entregada'];
        if ($todosServiciosCompletos && !in_array($orden->estatus, $estatusQueNoSobrescribir) && $orden->estatus !== 'completada') {
            $orden->estatus = 'completada';
            $orden->save();
            \Log::info("Orden {$orden->id} actualizada a COMPLETADA automáticamente (todos los servicios al 100%)");
        }

        return Inertia::render('OTMultiServicio/Show', [
            'orden' => [
                'id' => $orden->id,
                'descripcion_general' => $orden->descripcion_general,
                'estatus' => $orden->estatus,
                'calidad_resultado' => $orden->calidad_resultado,
                'subtotal' => $orden->subtotal,
                'iva' => $orden->iva,
                'total' => $orden->total,
                'created_at' => $orden->created_at->format('Y-m-d H:i'),
                'centro' => $orden->centro->only(['id', 'nombre']),
                'area' => $orden->area ? $orden->area->only(['id', 'nombre']) : null,
                'team_leader' => $orden->teamLeader ? $orden->teamLeader->only(['id', 'name']) : null,
            ],
            'servicios' => $serviciosData,
        ]);
    }

    /**
     * Registrar faltantes para un servicio específico
     */
    public function registrarFaltantesServicio(\Illuminate\Http\Request $request, Orden $orden, $servicioId)
    {
        // Verificar autorización
        $this->authorizeFromCentro($orden->id_centrotrabajo, $orden);

        // Validar entrada
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id_item' => ['required', 'integer', 'exists:ot_servicio_items,id'],
            'items.*.faltantes' => ['required', 'integer', 'min:0'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ]);

        $resumen = [];

        DB::transaction(function () use ($orden, $servicioId, $data, &$resumen) {
            // Buscar el servicio
            $otServicio = OTServicio::where('id', $servicioId)
                ->where('ot_id', $orden->id)
                ->lockForUpdate()
                ->firstOrFail();

            foreach ($data['items'] as $d) {
                $item = OTServicioItem::where('id', $d['id_item'])
                    ->where('ot_servicio_id', $otServicio->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $falt = max(0, (int)$d['faltantes']);
                
                if ($falt <= 0) continue;
                
                // Validar que no exceda lo planeado
                $nuevoTotal = (int)$item->completado + (int)($item->faltante ?? 0) + $falt;
                if ($nuevoTotal > (int)$item->planeado) {
                    $pendiente = max(0, (int)$item->planeado - (int)$item->completado - (int)($item->faltante ?? 0));
                    return response()->json([
                        'message' => "No se pueden registrar {$falt} faltantes para '{$item->descripcion_item}'. Solo quedan {$pendiente} unidades pendientes.",
                        'errors' => [
                            'items' => ["El item '{$item->descripcion_item}' excede lo planeado."]
                        ]
                    ], 422);
                }

                // Acumular faltantes SIN modificar el planeado
                $item->faltante = (int)($item->faltante ?? 0) + (int)$falt;
                $item->save();

                // Registrar avance individual por cada item con faltantes
                \App\Models\OTServicioAvance::create([
                    'ot_servicio_id' => $otServicio->id,
                    'tarifa' => 'NORMAL',
                    'precio_unitario_aplicado' => 0,
                    'cantidad_registrada' => $falt, // Cantidad de faltantes para este item
                    'comentario' => "[FALTANTES] {$item->descripcion_item}: {$falt} faltante(s)" . (!empty($data['nota']) ? " | Nota: {$data['nota']}" : ''),
                    'created_by' => Auth::id(),
                ]);

                $resumen[] = [
                    'id_item' => $item->id,
                    'descripcion' => $item->descripcion_item ?: 'Item',
                    'faltantes' => $falt,
                ];
            }

            // NO crear un avance global, ya se crearon individuales arriba
            // Comentado el código antiguo que creaba un solo avance
            /*
            // Registrar avance con faltantes en el historial
            $resumenTexto = collect($resumen)->map(function($r) {
                return "{$r['descripcion']}: {$r['faltantes']} faltante(s)";
            })->join(', ');
            
            $comentarioFaltantes = '[FALTANTES] ' . $resumenTexto;
            if (!empty($data['nota'])) {
                $comentarioFaltantes .= ' | Nota: ' . $data['nota'];
            }
            
            \App\Models\OTServicioAvance::create([
                'ot_servicio_id' => $otServicio->id,
                'tarifa' => 'NORMAL',
                'precio_unitario_aplicado' => 0,
                'cantidad_registrada' => 0,
                'comentario' => $comentarioFaltantes,
                'created_by' => Auth::id(),
            ]);
            */
            // Recalcular totales del servicio
            $totales = $otServicio->calcularTotales();
            
            // Actualizar subtotal del servicio basado en completado
            if ($otServicio->tipo_cobro === 'unidad') {
                $otServicio->subtotal = $totales['completado'] * $otServicio->precio_unitario;
                $otServicio->save();
            }

            // Recalcular totales de la orden
            $subtotalOT = $orden->otServicios()->sum('subtotal');
            $iva = round($subtotalOT * 0.16, 2);
            $total = round($subtotalOT + $iva, 2);

            $orden->update([
                'subtotal' => $subtotalOT,
                'iva' => $iva,
                'total' => $total,
            ]);
        });

        // Log de actividad
        activity()
            ->performedOn($orden)
            ->causedBy(Auth::user())
            ->withProperties([
                'servicio_id' => $servicioId,
                'faltantes' => $resumen,
                'nota' => $data['nota'] ?? null,
            ])
            ->log("Faltantes registrados en servicio #{$servicioId} de OT #{$orden->id}");

        return back()->with('ok', 'Faltantes registrados correctamente.');
    }

    /**
     * Helper: Autorización por centro de trabajo
     */
    private function authorizeFromCentro(?int $centroId, $model = null)
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Usuario no autenticado.');
        }

        // Admin y roles especiales pueden acceder a cualquier centro
        if ($user->hasAnyRole(['admin', 'control', 'comercial', 'calidad', 'facturacion'])) {
            return;
        }

        // Para otros usuarios, verificar que pertenezcan al centro
        if ($user->centro_trabajo_id !== $centroId) {
            abort(403, 'No tienes acceso a este centro de trabajo.');
        }
    }
}
