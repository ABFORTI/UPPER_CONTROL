<?php

namespace Tests\Feature;

use App\Models\CentroTrabajo;
use App\Models\Orden;
use App\Models\OTServicio;
use App\Models\OTServicioItem;
use App\Models\ServicioEmpresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OtDetalleAjusteTest extends TestCase
{
    use RefreshDatabase;

    protected function crearContextoBase(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('admin');

        $centro = CentroTrabajo::create([
            'nombre' => 'Centro Test',
            'prefijo' => 'CTST',
        ]);

        $servicio = ServicioEmpresa::create([
            'nombre' => 'Servicio Test Ajustes',
            'usa_tamanos' => false,
        ]);

        $centroCostoId = DB::table('centros_costos')->insertGetId([
            'id_centrotrabajo' => $centro->id,
            'nombre' => 'CC TEST',
            'activo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $solicitud = \App\Models\Solicitud::create([
            'folio' => 'SOL-TEST-0001',
            'id_cliente' => $user->id,
            'id_centrotrabajo' => $centro->id,
            'id_servicio' => $servicio->id,
            'id_centrocosto' => $centroCostoId,
            'descripcion' => 'Solicitud test',
            'cantidad' => 10,
            'estatus' => 'aprobada',
        ]);

        $ot = Orden::create([
            'id_solicitud' => $solicitud->id,
            'id_centrotrabajo' => $centro->id,
            'id_servicio' => $servicio->id,
            'team_leader_id' => null,
            'descripcion_general' => 'OT de prueba ajustes',
            'estatus' => 'en_proceso',
            'calidad_resultado' => 'pendiente',
            'total_planeado' => 0,
            'total_real' => 0,
            'subtotal' => 0,
            'iva' => 0,
            'total' => 0,
            'ot_status' => 'active',
        ]);

        $otServicio = OTServicio::create([
            'ot_id' => $ot->id,
            'servicio_id' => $servicio->id,
            'tipo_cobro' => 'pieza',
            'cantidad' => 10,
            'precio_unitario' => 10,
            'subtotal' => 100,
        ]);

        $detalle = OTServicioItem::create([
            'ot_servicio_id' => $otServicio->id,
            'descripcion_item' => 'Producto A',
            'planeado' => 10,
            'completado' => 2,
            'faltante' => 0,
            'precio_unitario' => 10,
            'subtotal' => 100,
        ]);

        return compact('user', 'ot', 'detalle', 'otServicio');
    }

    /** @test */
    public function registra_ajuste_extra_y_recalcula_totales_cobrables()
    {
        ['user' => $user, 'ot' => $ot, 'detalle' => $detalle, 'otServicio' => $otServicio] = $this->crearContextoBase();

        $response = $this->actingAs($user)->post(
            route('ot-detalles-ajustes.store', ['ot' => $ot->id, 'detalle' => $detalle->id]),
            [
                'tipo' => 'extra',
                'cantidad' => 5,
                'motivo' => 'Demanda adicional del cliente',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ot_ajustes_detalle', [
            'ot_id' => $ot->id,
            'ot_detalle_id' => $detalle->id,
            'tipo' => 'extra',
            'cantidad' => 5,
            'user_id' => $user->id,
        ]);

        $detalle->refresh();
        $met = $detalle->calcularMetricas();

        $this->assertSame(10, $met['solicitado']);
        $this->assertSame(5, $met['extra']);
        $this->assertSame(0, $met['faltantes']);
        $this->assertSame(15, $met['total_cobrable']);
        $this->assertSame(13, $met['pendiente']);

        $otServicio->refresh();
        $ot->refresh();

        $this->assertSame(150.0, (float) $otServicio->subtotal);
        $this->assertSame(150.0, (float) $ot->subtotal);
        $this->assertSame(174.0, (float) $ot->total);
    }

    /** @test */
    public function exige_motivo_cuando_el_tipo_es_extra()
    {
        ['user' => $user, 'ot' => $ot, 'detalle' => $detalle] = $this->crearContextoBase();

        $response = $this->from(route('ordenes.show', $ot->id))->actingAs($user)->post(
            route('ot-detalles-ajustes.store', ['ot' => $ot->id, 'detalle' => $detalle->id]),
            [
                'tipo' => 'extra',
                'cantidad' => 2,
                'motivo' => '',
            ]
        );

        $response->assertRedirect(route('ordenes.show', $ot->id));
        $response->assertSessionHasErrors(['motivo']);
        $this->assertDatabaseCount('ot_ajustes_detalle', 0);
    }

    /** @test */
    public function no_permite_ajustes_si_la_ot_esta_cerrada_o_facturada()
    {
        ['user' => $user, 'ot' => $ot, 'detalle' => $detalle] = $this->crearContextoBase();

        $ot->update([
            'estatus' => 'facturada',
        ]);

        $response = $this->actingAs($user)->post(
            route('ot-detalles-ajustes.store', ['ot' => $ot->id, 'detalle' => $detalle->id]),
            [
                'tipo' => 'faltante',
                'cantidad' => 1,
                'motivo' => null,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors(['orden']);
        $this->assertDatabaseCount('ot_ajustes_detalle', 0);
    }
}
