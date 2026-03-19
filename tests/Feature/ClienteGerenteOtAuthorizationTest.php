<?php

namespace Tests\Feature;

use App\Models\Aprobacion;
use App\Models\CentroCosto;
use App\Models\CentroTrabajo;
use App\Models\Orden;
use App\Models\ServicioEmpresa;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClienteGerenteOtAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private CentroTrabajo $centroA;
    private CentroTrabajo $centroB;
    private CentroCosto $centroCostoA;
    private ServicioEmpresa $servicio;
    private User $clienteSupervisorA;
    private User $clienteGerenteA;
    private User $clienteGerenteB;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'facturacion', 'Cliente_Supervisor', 'Cliente_Gerente'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $this->centroA = CentroTrabajo::create([
            'nombre' => 'Centro A',
            'numero_centro' => '001',
            'prefijo' => 'CA',
            'activo' => true,
        ]);

        $this->centroB = CentroTrabajo::create([
            'nombre' => 'Centro B',
            'numero_centro' => '002',
            'prefijo' => 'CB',
            'activo' => true,
        ]);

        $this->centroCostoA = CentroCosto::create([
            'id_centrotrabajo' => $this->centroA->id,
            'nombre' => 'CC A',
        ]);

        $this->servicio = ServicioEmpresa::create([
            'nombre' => 'Etiquetado',
            'usa_tamanos' => false,
        ]);

        $this->clienteSupervisorA = User::factory()->create([
            'centro_trabajo_id' => $this->centroA->id,
        ]);
        $this->clienteSupervisorA->assignRole('Cliente_Supervisor');

        $this->clienteGerenteA = User::factory()->create([
            'centro_trabajo_id' => $this->centroA->id,
        ]);
        $this->clienteGerenteA->assignRole('Cliente_Gerente');

        $this->clienteGerenteB = User::factory()->create([
            'centro_trabajo_id' => $this->centroB->id,
        ]);
        $this->clienteGerenteB->assignRole('Cliente_Gerente');
    }

    public function test_cliente_gerente_puede_autorizar_ot_de_su_centro_aunque_no_sea_dueno(): void
    {
        $solicitud = Solicitud::create([
            'folio' => 'SOL-CTA-001',
            'id_cliente' => $this->clienteSupervisorA->id,
            'id_centrotrabajo' => $this->centroA->id,
            'id_servicio' => $this->servicio->id,
            'id_centrocosto' => $this->centroCostoA->id,
            'estatus' => 'aprobada',
        ]);

        $orden = Orden::create([
            'id_solicitud' => $solicitud->id,
            'id_centrotrabajo' => $this->centroA->id,
            'id_servicio' => $this->servicio->id,
            'descripcion_general' => 'OT test centro A',
            'estatus' => 'completada',
            'calidad_resultado' => 'validado',
            'subtotal' => 100,
            'iva' => 16,
            'total' => 116,
        ]);

        $response = $this->actingAs($this->clienteGerenteA)
            ->post(route('cliente.autorizar', $orden));

        $response->assertStatus(302);

        $orden->refresh();
        $this->assertSame('autorizada_cliente', $orden->estatus);
        $this->assertNotNull($orden->cliente_autorizada_at);

        $this->assertDatabaseHas('aprobaciones', [
            'aprobable_type' => Orden::class,
            'aprobable_id' => $orden->id,
            'tipo' => 'cliente',
            'resultado' => 'aprobado',
            'id_usuario' => $this->clienteGerenteA->id,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'cliente_autoriza',
            'subject_type' => Orden::class,
            'subject_id' => $orden->id,
            'causer_id' => $this->clienteGerenteA->id,
        ]);
    }

    public function test_cliente_gerente_no_puede_autorizar_ot_de_otro_centro(): void
    {
        $solicitud = Solicitud::create([
            'folio' => 'SOL-CTA-002',
            'id_cliente' => $this->clienteSupervisorA->id,
            'id_centrotrabajo' => $this->centroA->id,
            'id_servicio' => $this->servicio->id,
            'id_centrocosto' => $this->centroCostoA->id,
            'estatus' => 'aprobada',
        ]);

        $orden = Orden::create([
            'id_solicitud' => $solicitud->id,
            'id_centrotrabajo' => $this->centroA->id,
            'id_servicio' => $this->servicio->id,
            'descripcion_general' => 'OT test otro centro',
            'estatus' => 'completada',
            'calidad_resultado' => 'validado',
            'subtotal' => 100,
            'iva' => 16,
            'total' => 116,
        ]);

        $this->actingAs($this->clienteGerenteB)
            ->post(route('cliente.autorizar', $orden))
            ->assertStatus(403);

        $orden->refresh();
        $this->assertSame('completada', $orden->estatus);
        $this->assertNull($orden->cliente_autorizada_at);

        $this->assertFalse(Aprobacion::query()
            ->where('aprobable_type', Orden::class)
            ->where('aprobable_id', $orden->id)
            ->where('tipo', 'cliente')
            ->exists());
    }

    public function test_cliente_gerente_no_puede_autorizar_si_no_esta_completada_o_validada_por_calidad(): void
    {
        $solicitud = Solicitud::create([
            'folio' => 'SOL-CTA-003',
            'id_cliente' => $this->clienteSupervisorA->id,
            'id_centrotrabajo' => $this->centroA->id,
            'id_servicio' => $this->servicio->id,
            'id_centrocosto' => $this->centroCostoA->id,
            'estatus' => 'aprobada',
        ]);

        $ordenSinCompletar = Orden::create([
            'id_solicitud' => $solicitud->id,
            'id_centrotrabajo' => $this->centroA->id,
            'id_servicio' => $this->servicio->id,
            'descripcion_general' => 'OT en proceso',
            'estatus' => 'en_proceso',
            'calidad_resultado' => 'validado',
            'subtotal' => 100,
            'iva' => 16,
            'total' => 116,
        ]);

        $ordenSinCalidad = Orden::create([
            'id_solicitud' => $solicitud->id,
            'id_centrotrabajo' => $this->centroA->id,
            'id_servicio' => $this->servicio->id,
            'descripcion_general' => 'OT sin calidad',
            'estatus' => 'completada',
            'calidad_resultado' => 'pendiente',
            'subtotal' => 100,
            'iva' => 16,
            'total' => 116,
        ]);

        $this->actingAs($this->clienteGerenteA)
            ->post(route('cliente.autorizar', $ordenSinCompletar))
            ->assertStatus(403);

        $this->actingAs($this->clienteGerenteA)
            ->post(route('cliente.autorizar', $ordenSinCalidad))
            ->assertStatus(403);
    }
}
