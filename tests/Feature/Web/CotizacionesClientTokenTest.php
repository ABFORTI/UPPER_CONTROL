<?php

namespace Tests\Feature\Web;

use App\Models\CentroCosto;
use App\Models\CentroTrabajo;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\CotizacionItemServicio;
use App\Models\ServicioEmpresa;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CotizacionesClientTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Para Feature tests con múltiples requests, SQLite :memory: puede comportarse como DB "vacía"
        // si se abren conexiones separadas. Usamos un archivo sqlite temporal para estabilidad.
        $this->useSqliteFileDatabase();

        $this->setUpMinimalSchema();

        // No necesitamos los shared props de Inertia para estos casos y requieren muchas tablas.
        $this->withoutMiddleware(\App\Http\Middleware\HandleInertiaRequests::class);
    }

    private function useSqliteFileDatabase(): void
    {
        $dbPath = database_path('testing-web.sqlite');
        if (!file_exists($dbPath)) {
            @touch($dbPath);
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $dbPath,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        // Reset completo del schema para evitar cross-test pollution.
        Schema::dropAllTables();
    }

    private function setUpMinimalSchema(): void
    {
        Schema::disableForeignKeyConstraints();

        // activity_log (User lo usa en este proyecto)
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
                $t->rememberToken();
                $t->timestamps();
            });
        }

        // spatie/permission (mínimo)
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
                $t->string('estatus', 20)->default(Cotizacion::ESTATUS_DRAFT);
                $t->timestamp('sent_at')->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->timestamp('rejected_at')->nullable();
                $t->timestamp('cancelled_at')->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->string('approval_token_hash', 64)->nullable();
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

        if (!Schema::hasTable('solicitudes')) {
            Schema::create('solicitudes', function (Blueprint $t) {
                $t->id();
                $t->string('folio')->unique();
                $t->unsignedBigInteger('id_cliente');
                $t->unsignedBigInteger('id_centrotrabajo');
                $t->unsignedBigInteger('id_servicio');
                $t->string('tamano')->nullable();
                $t->string('descripcion')->nullable();
                $t->unsignedInteger('cantidad')->default(1);
                $t->decimal('subtotal', 12, 2)->default(0);
                $t->decimal('iva', 12, 2)->default(0);
                $t->decimal('total', 12, 2)->default(0);
                $t->text('notas')->nullable();
                $t->string('estatus')->default('pendiente');
                $t->json('tamanos_json')->nullable();
                $t->json('metadata_json')->nullable();
                $t->unsignedBigInteger('id_cotizacion')->nullable();
                $t->unsignedBigInteger('id_cotizacion_item')->nullable();
                $t->unsignedBigInteger('id_cotizacion_item_servicio')->nullable();
                $t->timestamps();

                $t->index(['id_cotizacion']);
                $t->index(['id_cotizacion_item']);
                $t->index(['id_cotizacion_item_servicio']);
            });
        }

        Schema::enableForeignKeyConstraints();

        Role::firstOrCreate(['name' => 'Cliente_Supervisor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Cliente_Gerente', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'coordinador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    private function webCall(string $method, string $uri, array $payload = []): TestResponse
    {
        $server = [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ];

        $req = Request::create($uri, strtoupper($method), $payload, [], [], $server);

        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $res = $kernel->handle($req);
        $kernel->terminate($req, $res);

        return TestResponse::fromBaseResponse($res);
    }

    public function test_client_can_view_without_token_but_cannot_approve_or_reject(): void
    {
        $centro = CentroTrabajo::create(['nombre' => 'Centro 1', 'prefijo' => 'C1']);
        $cc = CentroCosto::create(['id_centrotrabajo' => $centro->id, 'nombre' => 'CC 1', 'activo' => true]);

        $client = User::create([
            'name' => 'Cliente',
            'email' => 'client_token_test@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $client->assignRole('Cliente_Supervisor');

        $creator = User::create([
            'name' => 'Coord',
            'email' => 'coord_token_test@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $creator->assignRole('coordinador');

        $plainToken = 'tok-123';

        $cotizacion = Cotizacion::create([
            'folio' => 'C1-COT-202601-0001',
            'created_by' => $creator->id,
            'id_cliente' => $client->id,
            'id_centrotrabajo' => $centro->id,
            'id_centrocosto' => $cc->id,
            'estatus' => Cotizacion::ESTATUS_SENT,
            'sent_at' => now(),
            'expires_at' => now()->addDays(2),
            'approval_token_hash' => hash('sha256', $plainToken),
            'subtotal' => 0,
            'iva' => 0,
            'total' => 0,
        ]);

        $this->assertNotNull(Cotizacion::find($cotizacion->id));

        $this->actingAs($client);

        // Ver pantalla sin token: permitido (campanita)
        $this->webCall('GET', "/client/quotations/{$cotizacion->id}")
            ->assertOk();

        // Acciones sin token: prohibidas
        $this->webCall('POST', "/cotizaciones/{$cotizacion->id}/approve")
            ->assertStatus(403);

        $this->webCall('POST', "/cotizaciones/{$cotizacion->id}/reject", ['motivo' => 'No procede'])
            ->assertStatus(403);
    }

    public function test_client_reject_requires_valid_token(): void
    {
        $centro = CentroTrabajo::create(['nombre' => 'Centro 1', 'prefijo' => 'C1']);
        $cc = CentroCosto::create(['id_centrotrabajo' => $centro->id, 'nombre' => 'CC 1', 'activo' => true]);

        $client = User::create([
            'name' => 'Cliente',
            'email' => 'client_token_test2@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $client->assignRole('Cliente_Supervisor');

        $creator = User::create([
            'name' => 'Coord',
            'email' => 'coord_token_test2@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $creator->assignRole('coordinador');

        $plainToken = 'tok-abc';

        $cotizacion = Cotizacion::create([
            'folio' => 'C1-COT-202601-0002',
            'created_by' => $creator->id,
            'id_cliente' => $client->id,
            'id_centrotrabajo' => $centro->id,
            'id_centrocosto' => $cc->id,
            'estatus' => Cotizacion::ESTATUS_SENT,
            'sent_at' => now(),
            'expires_at' => now()->addDays(2),
            'approval_token_hash' => hash('sha256', $plainToken),
            'subtotal' => 0,
            'iva' => 0,
            'total' => 0,
        ]);

        $this->assertNotNull(Cotizacion::find($cotizacion->id));

        $this->actingAs($client);

        // Token inválido => 403
        $this->webCall('POST', "/cotizaciones/{$cotizacion->id}/reject", [
            'motivo' => 'No',
            'token' => 'token-malo',
        ])->assertStatus(403);

        // Token válido => rechaza y redirige
        $this->webCall('POST', "/cotizaciones/{$cotizacion->id}/reject", [
            'motivo' => 'No procede',
            'token' => $plainToken,
        ])->assertStatus(302);

        $cotizacion->refresh();
        $this->assertSame(Cotizacion::ESTATUS_REJECTED, $cotizacion->estatus);
        $this->assertSame('No procede', $cotizacion->motivo_rechazo);
    }

    public function test_client_approve_with_valid_token_generates_solicitudes_and_is_idempotent(): void
    {
        $centro = CentroTrabajo::create(['nombre' => 'Centro 1', 'prefijo' => 'C1']);
        $cc = CentroCosto::create(['id_centrotrabajo' => $centro->id, 'nombre' => 'CC 1', 'activo' => true]);

        $client = User::create([
            'name' => 'Cliente',
            'email' => 'client_token_approve@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $client->assignRole('Cliente_Supervisor');

        $creator = User::create([
            'name' => 'Coord',
            'email' => 'coord_token_approve@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $creator->assignRole('coordinador');

        $plainToken = 'tok-approve-web';

        $cotizacion = Cotizacion::create([
            'folio' => 'C1-COT-202601-0100',
            'created_by' => $creator->id,
            'id_cliente' => $client->id,
            'id_centrotrabajo' => $centro->id,
            'id_centrocosto' => $cc->id,
            'estatus' => Cotizacion::ESTATUS_SENT,
            'sent_at' => now(),
            'expires_at' => now()->addDays(2),
            'approval_token_hash' => hash('sha256', $plainToken),
            'subtotal' => 200,
            'tax' => 32,
            'iva' => 32,
            'total' => 232,
        ]);

        $s1 = ServicioEmpresa::create(['nombre' => 'Servicio A', 'usa_tamanos' => false, 'activo' => true]);
        $s2 = ServicioEmpresa::create(['nombre' => 'Servicio B', 'usa_tamanos' => false, 'activo' => true]);

        $item = CotizacionItem::create([
            'cotizacion_id' => $cotizacion->id,
            'descripcion' => 'Item 1',
            'cantidad' => 1,
            'notas' => 'Notas item',
        ]);

        $line1 = CotizacionItemServicio::create([
            'cotizacion_item_id' => $item->id,
            'id_servicio' => $s1->id,
            'cantidad' => 1,
            'precio_unitario' => 100,
            'subtotal' => 100,
            'iva' => 16,
            'total' => 116,
        ]);
        $line2 = CotizacionItemServicio::create([
            'cotizacion_item_id' => $item->id,
            'id_servicio' => $s2->id,
            'cantidad' => 1,
            'precio_unitario' => 100,
            'subtotal' => 100,
            'iva' => 16,
            'total' => 116,
        ]);

        $this->actingAs($client);

        $this->webCall('POST', "/cotizaciones/{$cotizacion->id}/approve", [
            'token' => $plainToken,
        ])->assertStatus(302);

        $cotizacion->refresh();
        $this->assertSame(Cotizacion::ESTATUS_APPROVED, $cotizacion->estatus);
        $this->assertNull($cotizacion->approval_token_hash);

        $this->assertSame(2, Solicitud::where('id_cotizacion', (int)$cotizacion->id)->count());

        $first = Solicitud::where('id_cotizacion', (int)$cotizacion->id)->first();
        $this->assertNotNull($first);
        $this->assertSame((int)$cotizacion->id, (int)$first->id_cotizacion);
        $this->assertNotNull($first->id_cotizacion_item);
        $this->assertNotNull($first->id_cotizacion_item_servicio);

        // Idempotencia: re-disparar el evento no debe duplicar solicitudes
        event(new \App\Events\QuotationApproved((int)$cotizacion->id, (int)$cotizacion->id_cliente));
        $this->assertSame(2, Solicitud::where('id_cotizacion', (int)$cotizacion->id)->count());

        // sanity: trazabilidad apunta a líneas existentes
        $this->assertTrue(Solicitud::where('id_cotizacion_item_servicio', (int)$line1->id)->exists());
        $this->assertTrue(Solicitud::where('id_cotizacion_item_servicio', (int)$line2->id)->exists());
    }
}
