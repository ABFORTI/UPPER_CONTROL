<?php

namespace Tests\Feature;

use App\Actions\AssignPendingServiceAction;
use App\Models\CentroCosto;
use App\Models\CentroTrabajo;
use App\Models\Orden;
use App\Models\OTServicio;
use App\Models\OTServicioItem;
use App\Models\ServicioEmpresa;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PendingServiceAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $coordinador;
    private User $teamLeader;
    private User $cliente;
    private CentroTrabajo $centro;
    private CentroCosto $centroCosto;
    private ServicioEmpresa $servicio;
    private Solicitud $solicitud;
    private Orden $orden;
    private OTServicio $pendingOtServicio;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        foreach (['admin', 'coordinador', 'team_leader', 'calidad', 'Cliente_Supervisor'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        // Create users with roles
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->coordinador = User::factory()->create();
        $this->coordinador->assignRole('coordinador');

        $this->teamLeader = User::factory()->create();
        $this->teamLeader->assignRole('team_leader');

        $this->cliente = User::factory()->create();
        $this->cliente->assignRole('Cliente_Supervisor');

        // Create centro
        $this->centro = CentroTrabajo::create([
            'nombre' => 'Centro Test',
            'numero_centro' => '001',
            'prefijo' => 'CT',
            'activo' => true,
        ]);

        // Create service
        $this->servicio = ServicioEmpresa::create([
            'nombre' => 'Etiquetado',
            'usa_tamanos' => false,
        ]);

        // Create solicitud (required FK for ordenes_trabajo)
        $this->centroCosto = CentroCosto::create([
            'id_centrotrabajo' => $this->centro->id,
            'nombre' => 'General',
        ]);

        $this->solicitud = Solicitud::create([
            'folio' => 'UMX-TEST-0001',
            'id_cliente' => $this->cliente->id,
            'id_centrotrabajo' => $this->centro->id,
            'id_servicio' => $this->servicio->id,
            'id_centrocosto' => $this->centroCosto->id,
            'estatus' => 'aprobada',
        ]);

        // Create orden
        $this->orden = Orden::create([
            'id_solicitud' => $this->solicitud->id,
            'id_centrotrabajo' => $this->centro->id,
            'descripcion_general' => 'Orden de prueba',
            'estatus' => 'generada',
            'team_leader_id' => $this->teamLeader->id,
            'subtotal' => 0,
            'iva' => 0,
            'total' => 0,
        ]);

        // Create a pending OT service (servicio_id = null)
        $this->pendingOtServicio = OTServicio::create([
            'ot_id' => $this->orden->id,
            'servicio_id' => null,
            'tipo_cobro' => 'pieza',
            'cantidad' => 100,
            'precio_unitario' => 0,
            'subtotal' => 0,
            'origen' => 'SOLICITADO',
            'sku' => 'SKU-001',
            'origen_customs' => 'China',
            'pedimento' => 'PED-2024-001',
            'service_assignment_status' => 'pending',
            'service_locked' => false,
        ]);

        // Create item for the service
        OTServicioItem::create([
            'ot_servicio_id' => $this->pendingOtServicio->id,
            'descripcion_item' => 'Item de prueba',
            'planeado' => 100,
            'completado' => 0,
            'faltante' => 0,
            'precio_unitario' => 0,
            'subtotal' => 0,
        ]);
    }

    /**
     * Test 1: Admin can assign a pending service successfully via HTTP endpoint.
     */
    public function test_admin_can_assign_pending_service(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('ordenes.servicios.assignService', [
                'orden' => $this->orden->id,
                'otServicio' => $this->pendingOtServicio->id,
            ]), [
                'service_id' => $this->servicio->id,
            ]);

        $response->assertStatus(302); // redirect back

        $this->pendingOtServicio->refresh();
        $this->assertEquals($this->servicio->id, $this->pendingOtServicio->servicio_id);
        $this->assertEquals('assigned', $this->pendingOtServicio->service_assignment_status);
        $this->assertTrue($this->pendingOtServicio->service_locked);
        $this->assertEquals($this->admin->id, $this->pendingOtServicio->service_assigned_by);
        $this->assertNotNull($this->pendingOtServicio->service_assigned_at);
    }

    /**
     * Test 2: Coordinador can also assign a pending service.
     */
    public function test_coordinador_can_assign_pending_service(): void
    {
        // Coordinador needs centro access for the policy
        $this->coordinador->centros()->attach($this->centro->id);

        $response = $this->actingAs($this->coordinador)
            ->post(route('ordenes.servicios.assignService', [
                'orden' => $this->orden->id,
                'otServicio' => $this->pendingOtServicio->id,
            ]), [
                'service_id' => $this->servicio->id,
            ]);

        $response->assertStatus(302);

        $this->pendingOtServicio->refresh();
        $this->assertEquals($this->servicio->id, $this->pendingOtServicio->servicio_id);
        $this->assertEquals('assigned', $this->pendingOtServicio->service_assignment_status);
    }

    /**
     * Test 3: Cliente (unauthorized role) cannot assign a pending service.
     */
    public function test_unauthorized_user_cannot_assign_service(): void
    {
        $response = $this->actingAs($this->cliente)
            ->post(route('ordenes.servicios.assignService', [
                'orden' => $this->orden->id,
                'otServicio' => $this->pendingOtServicio->id,
            ]), [
                'service_id' => $this->servicio->id,
            ]);

        // Should be forbidden (role middleware blocks it)
        $response->assertStatus(403);

        // Verify nothing changed
        $this->pendingOtServicio->refresh();
        $this->assertNull($this->pendingOtServicio->servicio_id);
        $this->assertEquals('pending', $this->pendingOtServicio->service_assignment_status);
    }

    /**
     * Test 4: Assignment is irreversible — cannot reassign once locked.
     */
    public function test_cannot_reassign_locked_service(): void
    {
        // First assign
        $this->actingAs($this->admin)
            ->post(route('ordenes.servicios.assignService', [
                'orden' => $this->orden->id,
                'otServicio' => $this->pendingOtServicio->id,
            ]), [
                'service_id' => $this->servicio->id,
            ]);

        $this->pendingOtServicio->refresh();
        $this->assertTrue($this->pendingOtServicio->service_locked);

        // Create a second service
        $otroServicio = ServicioEmpresa::create([
            'nombre' => 'Empacado',
            'usa_tamanos' => false,
        ]);

        // Try to reassign — should fail
        $response = $this->actingAs($this->admin)
            ->post(route('ordenes.servicios.assignService', [
                'orden' => $this->orden->id,
                'otServicio' => $this->pendingOtServicio->id,
            ]), [
                'service_id' => $otroServicio->id,
            ]);

        // Should fail with validation error (422) or redirect with errors
        $this->pendingOtServicio->refresh();
        $this->assertEquals($this->servicio->id, $this->pendingOtServicio->servicio_id);
    }

    /**
     * Test 5: Action class validates that OTServicio belongs to the given Orden.
     */
    public function test_action_validates_ot_servicio_belongs_to_orden(): void
    {
        $otraSolicitud = Solicitud::create([
            'folio' => 'UMX-TEST-0002',
            'id_cliente' => $this->cliente->id,
            'id_centrotrabajo' => $this->centro->id,
            'id_servicio' => $this->servicio->id,
            'id_centrocosto' => $this->centroCosto->id,
            'estatus' => 'aprobada',
        ]);

        $otraOrden = Orden::create([
            'id_solicitud' => $otraSolicitud->id,
            'id_centrotrabajo' => $this->centro->id,
            'descripcion_general' => 'Otra orden',
            'estatus' => 'generada',
            'subtotal' => 0, 'iva' => 0, 'total' => 0,
        ]);

        $action = app(AssignPendingServiceAction::class);

        $this->expectException(ValidationException::class);
        $action->execute($otraOrden, $this->pendingOtServicio, $this->servicio->id, $this->admin);
    }

    /**
     * Test 6: OTServicio model helpers work correctly.
     */
    public function test_ot_servicio_model_helpers(): void
    {
        // Initially pending
        $this->assertTrue($this->pendingOtServicio->isServicePending());
        $this->assertFalse($this->pendingOtServicio->isServiceAssigned());
        $this->assertFalse($this->pendingOtServicio->isServiceLocked());
        $this->assertTrue($this->pendingOtServicio->canAssignService());

        // After assignment
        $this->pendingOtServicio->update([
            'servicio_id' => $this->servicio->id,
            'service_assignment_status' => 'assigned',
            'service_locked' => true,
            'service_assigned_at' => now(),
            'service_assigned_by' => $this->admin->id,
        ]);
        $this->pendingOtServicio->refresh();

        $this->assertFalse($this->pendingOtServicio->isServicePending());
        $this->assertTrue($this->pendingOtServicio->isServiceAssigned());
        $this->assertTrue($this->pendingOtServicio->isServiceLocked());
        $this->assertFalse($this->pendingOtServicio->canAssignService());
    }

    /**
     * Test 7: Orden model hasPendingServiceItems helper works.
     */
    public function test_orden_has_pending_service_items(): void
    {
        // With the pending service
        $this->assertTrue($this->orden->hasPendingServiceItems());

        // After assignment
        $this->pendingOtServicio->update([
            'servicio_id' => $this->servicio->id,
            'service_assignment_status' => 'assigned',
            'service_locked' => true,
        ]);

        // Clear cache
        $this->orden->refresh();
        $this->assertFalse($this->orden->hasPendingServiceItems());
    }

    /**
     * Test 8: SKU/origen_customs/pedimento are preserved after assignment.
     */
    public function test_sku_origen_pedimento_preserved_after_assignment(): void
    {
        $action = app(AssignPendingServiceAction::class);
        $action->execute($this->orden, $this->pendingOtServicio, $this->servicio->id, $this->admin);

        $this->pendingOtServicio->refresh();

        // SKU/origen/pedimento should remain unchanged
        $this->assertEquals('SKU-001', $this->pendingOtServicio->sku);
        $this->assertEquals('China', $this->pendingOtServicio->origen_customs);
        $this->assertEquals('PED-2024-001', $this->pendingOtServicio->pedimento);

        // But service should be assigned
        $this->assertEquals($this->servicio->id, $this->pendingOtServicio->servicio_id);
        $this->assertEquals('assigned', $this->pendingOtServicio->service_assignment_status);
    }
}
