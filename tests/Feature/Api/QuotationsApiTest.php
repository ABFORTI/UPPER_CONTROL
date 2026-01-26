<?php

namespace Tests\Feature\Api;

use App\Models\CentroCosto;
use App\Models\CentroTrabajo;
use App\Models\Cotizacion;
use App\Models\ServicioCentro;
use App\Models\ServicioEmpresa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use App\Notifications\QuotationSentNotification;
use Tests\TestCase;

class QuotationsApiTest extends TestCase
{
    use WithFaker;

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];

        $content = empty($payload) ? null : json_encode($payload);

        $req = Request::create($uri, strtoupper($method), [], [], [], $server, $content);

        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $res = $kernel->handle($req);
        $kernel->terminate($req, $res);

        return TestResponse::fromBaseResponse($res);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMinimalSchema();
    }

    private function setUpMinimalSchema(): void
    {
        Schema::disableForeignKeyConstraints();

        // spatie/laravel-activitylog (User y varios modelos lo usan)
        if (!Schema::hasTable('activity_log')) {
            Schema::create('activity_log', function (Blueprint $t) {
                $t->id();
                $t->string('log_name')->nullable();
                $t->text('description');
                $t->nullableMorphs('subject');
                $t->nullableMorphs('causer');
                $t->json('properties')->nullable();
                $t->uuid('batch_uuid')->nullable();
                $t->string('event')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('centros_trabajo')) {
            Schema::create('centros_trabajo', function (Blueprint $t) {
                $t->id();
                $t->string('nombre');
                $t->string('prefijo')->nullable();
                $t->string('numero_centro')->nullable();
                $t->string('direccion')->nullable();
                $t->boolean('activo')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('centro_trabajo_id')->nullable();
                $t->string('name');
                $t->string('email')->unique();
                $t->timestamp('email_verified_at')->nullable();
                $t->string('password')->nullable();
                $t->string('telefono')->nullable();
                $t->string('puesto')->nullable();
                $t->rememberToken();
                $t->timestamps();
            });
        }

        // spatie/permission (mínimo para middleware role + HasRoles)
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('guard_name');
                $t->timestamps();
                $t->unique(['name', 'guard_name']);
            });
        }

        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('guard_name');
                $t->timestamps();
                $t->unique(['name', 'guard_name']);
            });
        }

        if (!Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $t) {
                $t->unsignedBigInteger('permission_id');
                $t->unsignedBigInteger('role_id');
                $t->index(['role_id']);
            });
        }

        if (!Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $t) {
                $t->unsignedBigInteger('permission_id');
                $t->string('model_type');
                $t->unsignedBigInteger('model_id');
                $t->index(['model_id', 'model_type']);
            });
        }

        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $t) {
                $t->unsignedBigInteger('role_id');
                $t->string('model_type');
                $t->unsignedBigInteger('model_id');
                $t->index(['model_id', 'model_type']);
            });
        }

        if (!Schema::hasTable('centros_costos')) {
            Schema::create('centros_costos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('id_centrotrabajo');
                $t->string('nombre');
                $t->boolean('activo')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('marcas')) {
            Schema::create('marcas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('id_centrotrabajo');
                $t->string('nombre');
                $t->boolean('activo')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('areas')) {
            Schema::create('areas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('id_centrotrabajo');
                $t->string('nombre');
                $t->text('descripcion')->nullable();
                $t->boolean('activo')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('servicios_empresa')) {
            Schema::create('servicios_empresa', function (Blueprint $t) {
                $t->id();
                $t->string('nombre');
                $t->boolean('usa_tamanos')->default(false);
                $t->boolean('activo')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('servicios_centro')) {
            Schema::create('servicios_centro', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('id_centrotrabajo');
                $t->unsignedBigInteger('id_servicio');
                $t->decimal('precio_base', 10, 2)->default(0);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('servicio_tamanos')) {
            Schema::create('servicio_tamanos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('id_servicio_centro');
                $t->string('tamano');
                $t->decimal('precio', 10, 2)->default(0);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('cotizaciones')) {
            Schema::create('cotizaciones', function (Blueprint $t) {
                $t->id();
                $t->string('folio')->unique();
                $t->unsignedBigInteger('created_by');
                $t->unsignedBigInteger('id_cliente');
                $t->unsignedBigInteger('id_centrotrabajo');
                $t->unsignedBigInteger('id_centrocosto');
                $t->unsignedBigInteger('id_marca')->nullable();
                $t->unsignedBigInteger('id_area')->nullable();
                $t->string('currency', 3)->default('MXN');
                $t->decimal('subtotal', 12, 2)->default(0);
                $t->decimal('tax', 12, 2)->default(0);
                $t->decimal('iva', 12, 2)->default(0);
                $t->decimal('total', 12, 2)->default(0);
                $t->string('estatus')->default(Cotizacion::ESTATUS_DRAFT);
                $t->timestamp('sent_at')->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->timestamp('rejected_at')->nullable();
                $t->timestamp('cancelled_at')->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->string('approval_token_hash')->nullable();
                $t->text('notas')->nullable();
                $t->text('notes')->nullable();
                $t->text('motivo_rechazo')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('cotizacion_items')) {
            Schema::create('cotizacion_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cotizacion_id');
                $t->string('descripcion');
                $t->unsignedInteger('cantidad')->default(1);
                $t->text('notas')->nullable();

                $t->string('product_name')->nullable();
                $t->decimal('quantity', 12, 3)->nullable();
                $t->string('unit', 20)->nullable();
                $t->unsignedBigInteger('centro_costo_id')->nullable();
                $t->unsignedBigInteger('brand_id')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('cotizacion_item_servicios')) {
            Schema::create('cotizacion_item_servicios', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cotizacion_item_id');
                $t->unsignedBigInteger('id_servicio');
                $t->string('tamano')->nullable();
                $t->json('tamanos_json')->nullable();
                $t->unsignedInteger('cantidad')->default(1);
                $t->decimal('qty', 12, 3)->nullable();
                $t->decimal('precio_unitario', 12, 2)->default(0);
                $t->decimal('subtotal', 12, 2)->default(0);
                $t->decimal('iva', 12, 2)->default(0);
                $t->decimal('total', 12, 2)->default(0);
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        Schema::enableForeignKeyConstraints();

        // Roles base usados en el módulo
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'coordinador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Cliente_Supervisor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Cliente_Gerente', 'guard_name' => 'web']);
    }

    public function test_api_requires_authentication(): void
    {
        $this->apiJson('GET', '/api/quotations')
            ->assertStatus(401);
    }

    public function test_api_requires_coordinator_role(): void
    {
        $centro = CentroTrabajo::create(['nombre' => 'Centro 1', 'prefijo' => 'C1']);

        $clientUser = User::create([
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $clientUser->assignRole('Cliente_Supervisor');

        Sanctum::actingAs($clientUser);

        $this->apiJson('GET', '/api/quotations')
            ->assertStatus(403);
    }

    public function test_coordinator_can_create_add_item_add_service_and_send(): void
    {
        Notification::fake();

        $centro = CentroTrabajo::create(['nombre' => 'Centro 1', 'prefijo' => 'C1']);
        $cc = CentroCosto::create(['id_centrotrabajo' => $centro->id, 'nombre' => 'CC 1', 'activo' => true]);

        $client = User::create([
            'name' => 'Cliente',
            'email' => 'cliente2@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $client->assignRole('Cliente_Supervisor');

        $coord = User::create([
            'name' => 'Coord',
            'email' => 'coord@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $coord->assignRole('coordinador');

        Sanctum::actingAs($coord);

        $resp = $this->apiJson('POST', '/api/quotations', [
            'client_id' => $client->id,
            'centro_costo_id' => $cc->id,
            'currency' => 'MXN',
            'notes' => 'API test',
        ])->assertStatus(201);

        $cotizacionId = (int)$resp->json('data.id');
        $this->assertGreaterThan(0, $cotizacionId);

        $itemResp = $this->apiJson('POST', "/api/quotations/{$cotizacionId}/items", [
            'description' => 'Item 1',
            'quantity' => 1,
        ])->assertStatus(201);

        $itemId = (int)$itemResp->json('data.id');
        $this->assertGreaterThan(0, $itemId);

        $servicio = ServicioEmpresa::create(['nombre' => 'Servicio 1', 'usa_tamanos' => false, 'activo' => true]);
        ServicioCentro::create([
            'id_centrotrabajo' => $centro->id,
            'id_servicio' => $servicio->id,
            'precio_base' => 100,
        ]);

        $this->apiJson('POST', "/api/quotation-items/{$itemId}/services", [
            'service_id' => $servicio->id,
            'quantity' => 1,
            'notes' => 'Servicio API',
        ])->assertStatus(201)
            ->assertJsonPath('data.service_id', $servicio->id);

        $send = $this->apiJson('POST', "/api/quotations/{$cotizacionId}/send", [
            'expires_days' => 3,
        ])->assertOk();

        $send->assertJsonPath('data.id', $cotizacionId);
        $send->assertJsonPath('data.status', Cotizacion::ESTATUS_SENT);
        $send->assertJsonStructure(['review_url', 'expires_at']);

        $fresh = Cotizacion::findOrFail($cotizacionId);
        $this->assertSame(Cotizacion::ESTATUS_SENT, $fresh->estatus);
        $this->assertNotNull($fresh->approval_token_hash);
        $this->assertNotNull($fresh->expires_at);

        Notification::assertSentTo($client, QuotationSentNotification::class);
    }

    public function test_send_without_items_returns_422(): void
    {
        Notification::fake();

        $centro = CentroTrabajo::create(['nombre' => 'Centro 1', 'prefijo' => 'C1']);
        $cc = CentroCosto::create(['id_centrotrabajo' => $centro->id, 'nombre' => 'CC 1', 'activo' => true]);

        $client = User::create([
            'name' => 'Cliente',
            'email' => 'cliente3@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $client->assignRole('Cliente_Supervisor');

        $coord = User::create([
            'name' => 'Coord',
            'email' => 'coord2@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $coord->assignRole('coordinador');

        $cot = Cotizacion::create([
            'folio' => 'C1-COT-202601-0001',
            'created_by' => $coord->id,
            'id_cliente' => $client->id,
            'id_centrotrabajo' => $centro->id,
            'id_centrocosto' => $cc->id,
            'estatus' => Cotizacion::ESTATUS_DRAFT,
            'currency' => 'MXN',
            'subtotal' => 0,
            'tax' => 0,
            'iva' => 0,
            'total' => 0,
        ]);

        Sanctum::actingAs($coord);

        $this->apiJson('POST', "/api/quotations/{$cot->id}/send", ['expires_days' => 3])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_pdf_endpoint_returns_pdf_download(): void
    {
        $centro = CentroTrabajo::create(['nombre' => 'Centro 1', 'prefijo' => 'C1']);
        $cc = CentroCosto::create(['id_centrotrabajo' => $centro->id, 'nombre' => 'CC 1', 'activo' => true]);

        $client = User::create([
            'name' => 'Cliente',
            'email' => 'cliente_pdf@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $client->assignRole('Cliente_Supervisor');

        $coord = User::create([
            'name' => 'Coord',
            'email' => 'coord_pdf@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $coord->assignRole('coordinador');

        $cot = Cotizacion::create([
            'folio' => 'C1-COT-202601-0099',
            'created_by' => $coord->id,
            'id_cliente' => $client->id,
            'id_centrotrabajo' => $centro->id,
            'id_centrocosto' => $cc->id,
            'estatus' => Cotizacion::ESTATUS_DRAFT,
            'currency' => 'MXN',
            'subtotal' => 100,
            'tax' => 0,
            'iva' => 16,
            'total' => 116,
        ]);

        $item = \App\Models\CotizacionItem::create([
            'cotizacion_id' => $cot->id,
            'descripcion' => 'Item PDF',
            'cantidad' => 1,
            'notas' => 'Notas de item',
        ]);

        $servicio = ServicioEmpresa::create(['nombre' => 'Servicio PDF', 'usa_tamanos' => false, 'activo' => true]);
        ServicioCentro::create([
            'id_centrotrabajo' => $centro->id,
            'id_servicio' => $servicio->id,
            'precio_base' => 100,
        ]);

        \App\Models\CotizacionItemServicio::create([
            'cotizacion_item_id' => $item->id,
            'id_servicio' => $servicio->id,
            'cantidad' => 1,
            'qty' => 1,
            'precio_unitario' => 100,
            'subtotal' => 100,
            'iva' => 16,
            'total' => 116,
            'notes' => 'Notas del servicio',
        ]);

        Sanctum::actingAs($coord);

        $resp = $this->apiJson('GET', "/api/quotations/{$cot->id}/pdf")
            ->assertOk();

        $ct = (string)$resp->headers->get('content-type');
        $this->assertStringContainsString('application/pdf', strtolower($ct));

        $cd = (string)$resp->headers->get('content-disposition');
        $this->assertStringContainsString('attachment', strtolower($cd));
        $this->assertStringContainsString('.pdf', strtolower($cd));

        $this->assertStringStartsWith('%PDF', (string)$resp->getContent());
    }
}
