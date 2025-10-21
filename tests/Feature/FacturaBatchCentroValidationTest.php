<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Orden;
use App\Models\Solicitud;
use App\Models\CentroTrabajo;
use App\Models\ServicioEmpresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class FacturaBatchCentroValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $facturacionUser;
    protected CentroTrabajo $centro1;
    protected CentroTrabajo $centro2;
    protected ServicioEmpresa $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::firstOrCreate(['name' => 'facturacion']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cliente']);
        
        // Create facturacion user
        $this->facturacionUser = User::factory()->create();
        $this->facturacionUser->assignRole('facturacion');
        
        // Create centros
        $this->centro1 = CentroTrabajo::create([
            'nombre' => 'Centro Test 1',
            'prefijo' => 'CT1',
            'activo' => true,
        ]);
        
        $this->centro2 = CentroTrabajo::create([
            'nombre' => 'Centro Test 2',
            'prefijo' => 'CT2',
            'activo' => true,
        ]);
        
        // Create servicio
        $this->servicio = ServicioEmpresa::create([
            'nombre' => 'Servicio Test',
        ]);
    }

    public function test_batch_create_rejects_multiple_centros()
    {
        // Create cliente
        $cliente = User::factory()->create();
        $cliente->assignRole('cliente');
        
        // Create orders from different centros
        $solicitud1 = Solicitud::create([
            'id_cliente' => $cliente->id,
            'id_servicio' => $this->servicio->id,
            'id_centrotrabajo' => $this->centro1->id,
            'estatus' => 'aprobada',
            'descripcion' => 'Test 1',
        ]);
        
        $orden1 = Orden::create([
            'id_solicitud' => $solicitud1->id,
            'id_servicio' => $this->servicio->id,
            'id_centrotrabajo' => $this->centro1->id,
            'estatus' => 'autorizada_cliente',
            'total' => 1000,
        ]);
        
        $solicitud2 = Solicitud::create([
            'id_cliente' => $cliente->id,
            'id_servicio' => $this->servicio->id,
            'id_centrotrabajo' => $this->centro2->id,
            'estatus' => 'aprobada',
            'descripcion' => 'Test 2',
        ]);
        
        $orden2 = Orden::create([
            'id_solicitud' => $solicitud2->id,
            'id_servicio' => $this->servicio->id,
            'id_centrotrabajo' => $this->centro2->id,
            'estatus' => 'autorizada_cliente',
            'total' => 2000,
        ]);
        
        // Attempt to create batch with orders from different centros
        $response = $this->actingAs($this->facturacionUser)
            ->get(route('facturas.batch.create', ['ids' => implode(',', [$orden1->id, $orden2->id])]));
        
        // Should return 422 with validation error
        $response->assertStatus(422);
    }

    public function test_batch_create_accepts_same_centro()
    {
        // Create cliente
        $cliente = User::factory()->create();
        $cliente->assignRole('cliente');
        
        // Create orders from SAME centro
        $solicitud1 = Solicitud::create([
            'id_cliente' => $cliente->id,
            'id_servicio' => $this->servicio->id,
            'id_centrotrabajo' => $this->centro1->id,
            'estatus' => 'aprobada',
            'descripcion' => 'Test 1',
        ]);
        
        $orden1 = Orden::create([
            'id_solicitud' => $solicitud1->id,
            'id_servicio' => $this->servicio->id,
            'id_centrotrabajo' => $this->centro1->id,
            'estatus' => 'autorizada_cliente',
            'total' => 1000,
        ]);
        
        $solicitud2 = Solicitud::create([
            'id_cliente' => $cliente->id,
            'id_servicio' => $this->servicio->id,
            'id_centrotrabajo' => $this->centro1->id,
            'estatus' => 'aprobada',
            'descripcion' => 'Test 2',
        ]);
        
        $orden2 = Orden::create([
            'id_solicitud' => $solicitud2->id,
            'id_servicio' => $this->servicio->id,
            'id_centrotrabajo' => $this->centro1->id,
            'estatus' => 'autorizada_cliente',
            'total' => 2000,
        ]);
        
        // Attempt to create batch with orders from same centro
        $response = $this->actingAs($this->facturacionUser)
            ->get(route('facturas.batch.create', ['ids' => implode(',', [$orden1->id, $orden2->id])]));
        
        // Should succeed and show the CreateBatch view
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Facturas/CreateBatch')
            ->has('ordenes', 2)
            ->where('ordenes.0.centro', 'Centro Test 1')
            ->where('ordenes.1.centro', 'Centro Test 1')
        );
    }

    public function test_batch_requires_facturacion_role()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->get(route('facturas.batch.create', ['ids' => '1,2']));
        
        $response->assertStatus(403);
    }
}
