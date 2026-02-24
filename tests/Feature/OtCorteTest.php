<?php

namespace Tests\Feature;

use App\Models\Orden;
use App\Models\OtCorte;
use App\Models\OtCorteDetalle;
use App\Models\OTServicio;
use App\Models\OTServicioAvance;
use App\Models\OTServicioItem;
use App\Models\User;
use App\Services\OtSplitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtCorteTest extends TestCase
{
    use RefreshDatabase;

    protected OtSplitService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OtSplitService::class);

        // Crear un usuario de prueba
        $this->user = User::factory()->create();
    }

    /**
     * Helper: crea una OT con servicios y avances para testing.
     */
    protected function crearOtConServicios(int $cantidadServicios = 2): Orden
    {
        $ot = Orden::factory()->create([
            'ot_status' => 'active',
        ]);

        for ($i = 0; $i < $cantidadServicios; $i++) {
            $srv = OTServicio::create([
                'ot_id'          => $ot->id,
                'servicio_id'    => 1, // asume que existe
                'tipo_cobro'     => 'pieza',
                'cantidad'       => 100,
                'precio_unitario' => 50.00,
                'subtotal'       => 5000.00,
            ]);

            // Crear items
            OTServicioItem::create([
                'ot_servicio_id' => $srv->id,
                'descripcion_item' => "Item servicio {$i}",
                'planeado'       => 100,
                'completado'     => 60,
                'faltante'       => 0,
            ]);

            // Crear avances (60 unidades ejecutadas)
            OTServicioAvance::create([
                'ot_servicio_id' => $srv->id,
                'tarifa'         => 'NORMAL',
                'precio_unitario_aplicado' => 50.00,
                'cantidad_registrada'      => 60,
                'comentario'    => 'Avance test',
                'created_by'    => $this->user->id,
            ]);
        }

        return $ot->fresh();
    }

    /** @test */
    public function preview_retorna_conceptos_correctamente()
    {
        $ot = $this->crearOtConServicios(2);

        $preview = $this->service->preview($ot);

        $this->assertCount(2, $preview);
        $this->assertEquals(100, $preview[0]['contratado']);
        $this->assertEquals(60, $preview[0]['ejecutado_total']);
        $this->assertEquals(0, $preview[0]['cortado_previo']);
        $this->assertEquals(60, $preview[0]['ejecutado_no_cortado']);
        $this->assertEquals(60, $preview[0]['sugerencia_cantidad_corte']);
        $this->assertEquals(50.00, $preview[0]['precio_unitario']);
        $this->assertEquals(3000.00, $preview[0]['importe_sugerido']);
    }

    /** @test */
    public function crear_corte_genera_registro_y_detalles()
    {
        $ot = $this->crearOtConServicios(1);
        $srv = $ot->otServicios->first();

        $corte = $this->service->crearCorte($ot, [
            'periodo_inicio' => '2026-02-16',
            'periodo_fin'    => '2026-02-22',
            'crear_ot_hija'  => false,
            'detalles'       => [
                ['ot_servicio_id' => $srv->id, 'cantidad_cortada' => 50],
            ],
        ], $this->user->id);

        $this->assertInstanceOf(OtCorte::class, $corte);
        $this->assertEquals('draft', $corte->estatus);
        $this->assertEquals(2500.00, (float) $corte->monto_total);
        $this->assertCount(1, $corte->detalles);
        $this->assertEquals(50, (float) $corte->detalles->first()->cantidad_cortada);
        $this->assertStringStartsWith('CORTE-', $corte->folio_corte);
    }

    /** @test */
    public function crear_corte_con_ot_hija()
    {
        $ot = $this->crearOtConServicios(1);
        $srv = $ot->otServicios->first();

        $corte = $this->service->crearCorte($ot, [
            'periodo_inicio' => '2026-02-16',
            'periodo_fin'    => '2026-02-22',
            'crear_ot_hija'  => true,
            'detalles'       => [
                ['ot_servicio_id' => $srv->id, 'cantidad_cortada' => 40],
            ],
        ], $this->user->id);

        // Verificar que se creó la OT hija
        $this->assertNotNull($corte->ot_hija_id);
        $otHija = Orden::find($corte->ot_hija_id);
        $this->assertNotNull($otHija);
        $this->assertEquals($ot->id, $otHija->parent_ot_id);
        $this->assertEquals('active', $otHija->ot_status);

        // Verificar remanente en servicios de la OT hija
        $srvHija = $otHija->otServicios->first();
        $this->assertNotNull($srvHija);
        $this->assertEquals(60, (float) $srvHija->cantidad); // 100 - 40 = 60

        // Verificar OT origen marcada como partial
        $ot->refresh();
        $this->assertEquals('partial', $ot->ot_status);
    }

    /** @test */
    public function no_permite_cortar_mas_del_ejecutado()
    {
        $ot = $this->crearOtConServicios(1);
        $srv = $ot->otServicios->first();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('excede el ejecutado no cortado');

        $this->service->crearCorte($ot, [
            'periodo_inicio' => '2026-02-16',
            'periodo_fin'    => '2026-02-22',
            'detalles'       => [
                ['ot_servicio_id' => $srv->id, 'cantidad_cortada' => 80], // solo 60 ejecutados
            ],
        ], $this->user->id);
    }

    /** @test */
    public function no_permite_detalles_vacios()
    {
        $ot = $this->crearOtConServicios(1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('al menos un concepto');

        $this->service->crearCorte($ot, [
            'periodo_inicio' => '2026-02-16',
            'periodo_fin'    => '2026-02-22',
            'detalles'       => [
                ['ot_servicio_id' => $ot->otServicios->first()->id, 'cantidad_cortada' => 0],
            ],
        ], $this->user->id);
    }

    /** @test */
    public function ot_se_cierra_cuando_todo_cortado()
    {
        $ot = $this->crearOtConServicios(1);
        $srv = $ot->otServicios->first();

        // Cortar la totalidad de lo contratado (ejecutado = 60, contratado = 100)
        // Primero cortamos 60 (todo lo ejecutado)
        $this->service->crearCorte($ot, [
            'periodo_inicio' => '2026-02-16',
            'periodo_fin'    => '2026-02-22',
            'crear_ot_hija'  => true,
            'detalles'       => [
                ['ot_servicio_id' => $srv->id, 'cantidad_cortada' => 60],
            ],
        ], $this->user->id);

        // Hay remanente (100 - 60 = 40), OT debería quedar partial
        $ot->refresh();
        $this->assertEquals('partial', $ot->ot_status);
    }

    /** @test */
    public function folio_corte_es_unico()
    {
        $ot = $this->crearOtConServicios(1);
        $srv = $ot->otServicios->first();

        // Crear primer corte
        $corte1 = $this->service->crearCorte($ot, [
            'periodo_inicio' => '2026-02-16',
            'periodo_fin'    => '2026-02-22',
            'crear_ot_hija'  => false,
            'detalles'       => [
                ['ot_servicio_id' => $srv->id, 'cantidad_cortada' => 20],
            ],
        ], $this->user->id);

        // Crear segundo corte
        $corte2 = $this->service->crearCorte($ot, [
            'periodo_inicio' => '2026-02-23',
            'periodo_fin'    => '2026-03-01',
            'crear_ot_hija'  => false,
            'detalles'       => [
                ['ot_servicio_id' => $srv->id, 'cantidad_cortada' => 20],
            ],
        ], $this->user->id);

        $this->assertNotEquals($corte1->folio_corte, $corte2->folio_corte);
    }

    /** @test */
    public function cortado_previo_se_acumula_correctamente()
    {
        $ot = $this->crearOtConServicios(1);
        $srv = $ot->otServicios->first();

        // Primer corte: 20 unidades
        $this->service->crearCorte($ot, [
            'periodo_inicio' => '2026-02-16',
            'periodo_fin'    => '2026-02-22',
            'crear_ot_hija'  => false,
            'detalles'       => [
                ['ot_servicio_id' => $srv->id, 'cantidad_cortada' => 20],
            ],
        ], $this->user->id);

        // Preview debe mostrar cortado_previo = 20
        $preview = $this->service->preview($ot);

        $this->assertEquals(20, $preview[0]['cortado_previo']);
        $this->assertEquals(40, $preview[0]['ejecutado_no_cortado']); // 60 - 20
    }

    /** @test */
    public function endpoint_preview_retorna_json()
    {
        $ot = $this->crearOtConServicios(1);

        $response = $this->actingAs($this->user)->postJson("/ots/{$ot->id}/cortes/preview", [
            'periodo_inicio' => '2026-02-16',
            'periodo_fin'    => '2026-02-22',
        ]);

        $response->assertOk()
                 ->assertJsonStructure([
                     'ot_id',
                     'conceptos' => [
                         '*' => [
                             'ot_servicio_id',
                             'servicio_nombre',
                             'contratado',
                             'ejecutado_total',
                             'cortado_previo',
                             'ejecutado_no_cortado',
                             'sugerencia_cantidad_corte',
                             'precio_unitario',
                             'importe_sugerido',
                         ],
                     ],
                 ]);
    }

    /** @test */
    public function endpoint_store_crea_corte()
    {
        $ot = $this->crearOtConServicios(1);
        $srv = $ot->otServicios->first();

        $response = $this->actingAs($this->user)->postJson("/ots/{$ot->id}/cortes", [
            'periodo_inicio' => '2026-02-16',
            'periodo_fin'    => '2026-02-22',
            'crear_ot_hija'  => true,
            'detalles'       => [
                ['ot_servicio_id' => $srv->id, 'cantidad_cortada' => 30],
            ],
        ]);

        $response->assertCreated()
                 ->assertJsonPath('success', true);

        $this->assertDatabaseHas('ot_cortes', [
            'ot_id' => $ot->id,
        ]);
    }

    /** @test */
    public function endpoint_show_retorna_detalle_corte()
    {
        $ot = $this->crearOtConServicios(1);
        $srv = $ot->otServicios->first();

        $corte = $this->service->crearCorte($ot, [
            'periodo_inicio' => '2026-02-16',
            'periodo_fin'    => '2026-02-22',
            'crear_ot_hija'  => false,
            'detalles'       => [
                ['ot_servicio_id' => $srv->id, 'cantidad_cortada' => 30],
            ],
        ], $this->user->id);

        $response = $this->actingAs($this->user)->getJson("/cortes/{$corte->id}");

        $response->assertOk()
                 ->assertJsonPath('id', $corte->id)
                 ->assertJsonPath('folio_corte', $corte->folio_corte);
    }
}
