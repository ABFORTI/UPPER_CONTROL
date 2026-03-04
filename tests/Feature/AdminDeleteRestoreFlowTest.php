<?php

namespace Tests\Feature;

use App\Models\Avance;
use App\Models\Factura;
use App\Models\Orden;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDeleteRestoreFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['activitylog.enabled' => false]);

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'coordinador']);
    }

    public function test_admin_can_soft_delete_and_restore_solicitud(): void
    {
        [$admin, $solicitud] = $this->seedSolicitudWithAdmin();

        $this->actingAs($admin)
            ->delete(route('solicitudes.destroy', $solicitud->id))
            ->assertSessionHas('ok');

        $this->assertSoftDeleted('solicitudes', ['id' => $solicitud->id]);

        $this->actingAs($admin)
            ->post(route('solicitudes.restore', $solicitud->id))
            ->assertSessionHas('ok');

        $this->assertDatabaseHas('solicitudes', [
            'id' => $solicitud->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_soft_delete_and_restore_orden(): void
    {
        [$admin, $orden] = $this->seedOrdenWithAdmin();

        $this->actingAs($admin)
            ->delete(route('ordenes.destroy', $orden->id))
            ->assertSessionHas('ok');

        $this->assertSoftDeleted('ordenes_trabajo', ['id' => $orden->id]);

        $this->actingAs($admin)
            ->post(route('ordenes.restore', $orden->id))
            ->assertSessionHas('ok');

        $this->assertDatabaseHas('ordenes_trabajo', [
            'id' => $orden->id,
            'deleted_at' => null,
        ]);
    }

    public function test_non_admin_gets_403_when_trying_delete_orden(): void
    {
        [, $orden] = $this->seedOrdenWithAdmin();

        $user = User::factory()->create([
            'centro_trabajo_id' => $orden->id_centrotrabajo,
        ]);
        $user->assignRole('coordinador');

        $this->actingAs($user)
            ->delete(route('ordenes.destroy', $orden->id))
            ->assertForbidden();
    }

    public function test_delete_orden_is_blocked_when_has_avances(): void
    {
        [$admin, $orden] = $this->seedOrdenWithAdmin();

        Avance::create([
            'id_orden' => $orden->id,
            'id_item' => null,
            'id_usuario' => $admin->id,
            'user_id' => $admin->id,
            'tipo' => 'NORMAL',
            'cantidad' => 1,
            'comentario' => 'avance test',
        ]);

        $this->actingAs($admin)
            ->delete(route('ordenes.destroy', $orden->id))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('ordenes_trabajo', [
            'id' => $orden->id,
            'deleted_at' => null,
        ]);
    }

    public function test_force_delete_orden_is_blocked_when_has_factura(): void
    {
        [$admin, $orden] = $this->seedOrdenWithAdmin();

        Factura::create([
            'id_orden' => $orden->id,
            'folio' => 'FAC-TEST-001',
            'folio_externo' => null,
            'total' => 100,
            'estatus' => 'facturado',
            'fecha_facturado' => now()->toDateString(),
            'fecha_cobro' => null,
            'fecha_pagado' => null,
        ]);

        $this->actingAs($admin)
            ->delete(route('ordenes.force', $orden->id), ['motivo' => 'Prueba de borrado definitivo'])
            ->assertSessionHasErrors('force');

        $this->assertDatabaseHas('ordenes_trabajo', ['id' => $orden->id]);
    }

    private function seedSolicitudWithAdmin(): array
    {
        $centroId = DB::table('centros_trabajo')->insertGetId([
            'nombre' => 'Centro Test',
            'prefijo' => 'CT' . random_int(100, 999),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $servicioId = DB::table('servicios_empresa')->insertGetId([
            'nombre' => 'Servicio Test ' . random_int(1000, 9999),
            'usa_tamanos' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $centroCostoId = DB::table('centros_costos')->insertGetId([
            'id_centrotrabajo' => $centroId,
            'nombre' => 'CC Test ' . random_int(100, 999),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create([
            'centro_trabajo_id' => $centroId,
        ]);
        $admin->assignRole('admin');

        $solicitud = Solicitud::create([
            'folio' => 'SOL-' . now()->format('YmdHis') . random_int(10, 99),
            'id_cliente' => $admin->id,
            'id_centrotrabajo' => $centroId,
            'id_servicio' => $servicioId,
            'id_centrocosto' => $centroCostoId,
            'descripcion' => 'Solicitud test',
            'cantidad' => 1,
            'estatus' => 'pendiente',
        ]);

        return [$admin, $solicitud];
    }

    private function seedOrdenWithAdmin(): array
    {
        [$admin, $solicitud] = $this->seedSolicitudWithAdmin();

        $orden = Orden::create([
            'id_solicitud' => $solicitud->id,
            'id_centrotrabajo' => $solicitud->id_centrotrabajo,
            'id_servicio' => $solicitud->id_servicio,
            'estatus' => 'generada',
            'total_planeado' => 1,
            'total_real' => 0,
        ]);

        return [$admin, $orden];
    }
}
