<?php

namespace Tests\Feature\Api;

use App\Models\CentroCosto;
use App\Models\CentroTrabajo;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\CotizacionItemServicio;
use App\Models\Solicitud;
use App\Models\ServicioEmpresa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ClientQuotationsApiTest extends TestCase
{
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

        // spatie/laravel-activitylog (User lo dispara en este proyecto)
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

        // Tablas mínimas para modelos/relaciones usadas en respuesta del endpoint
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

        if (!Schema::hasTable('cotizacion_audit_logs')) {
            Schema::create('cotizacion_audit_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cotizacion_id');
                $t->string('action', 50);
                $t->unsignedBigInteger('actor_user_id')->nullable();
                $t->unsignedBigInteger('actor_client_id')->nullable();
                $t->json('payload')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['cotizacion_id', 'created_at']);
                $t->index(['action']);
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
                $t->index(['id_cotizacion_item_servicio']);
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    private function makeSentQuotation(?string $plainToken = 'tok-123', bool $expired = false): array
    {
        $centro = CentroTrabajo::create(['nombre' => 'Centro 1', 'prefijo' => 'C1']);
        $cc = CentroCosto::create(['id_centrotrabajo' => $centro->id, 'nombre' => 'CC 1', 'activo' => true]);

        $client = User::create([
            'name' => 'Cliente',
            'email' => 'cliente+' . uniqid() . '@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);

        $creator = User::create([
            'name' => 'Coord',
            'email' => 'coord+' . uniqid() . '@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);

        $expiresAt = $expired ? now()->subMinutes(1) : now()->addDays(2);

        $cotizacion = Cotizacion::create([
            'folio' => 'C1-COT-202601-0001-' . uniqid(),
            'created_by' => $creator->id,
            'id_cliente' => $client->id,
            'id_centrotrabajo' => $centro->id,
            'id_centrocosto' => $cc->id,
            'estatus' => Cotizacion::ESTATUS_SENT,
            'sent_at' => now(),
            'expires_at' => $expiresAt,
            'approval_token_hash' => $plainToken ? hash('sha256', $plainToken) : null,
            'subtotal' => 100,
            'tax' => 16,
            'iva' => 16,
            'total' => 116,
        ]);

        $servicio = ServicioEmpresa::create(['nombre' => 'Servicio 1', 'usa_tamanos' => false, 'activo' => true]);
        $item = CotizacionItem::create([
            'cotizacion_id' => $cotizacion->id,
            'descripcion' => 'Item 1',
            'cantidad' => 1,
            'notas' => 'Notas item',
        ]);
        CotizacionItemServicio::create([
            'cotizacion_item_id' => $item->id,
            'id_servicio' => $servicio->id,
            'cantidad' => 1,
            'precio_unitario' => 100,
            'subtotal' => 100,
            'iva' => 16,
            'total' => 116,
        ]);

        return [$cotizacion, $plainToken];
    }

    public function test_client_api_get_requires_token_and_valid_token_and_sent_status(): void
    {
        [$cot, $token] = $this->makeSentQuotation('tok-abc', false);

        // Sin token
        $this->apiJson('GET', "/api/client/quotations/{$cot->id}")
            ->assertStatus(401)
            ->assertJsonPath('code', 'TOKEN_REQUIRED');

        // Token inválido
        $this->apiJson('GET', "/api/client/quotations/{$cot->id}?token=bad")
            ->assertStatus(401)
            ->assertJsonPath('code', 'TOKEN_INVALID');

        // Status != sent
        $cot->update(['estatus' => Cotizacion::ESTATUS_APPROVED]);
        $this->apiJson('GET', "/api/client/quotations/{$cot->id}?token={$token}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'STATUS_NOT_SENT');
    }

    public function test_client_api_get_returns_full_quotation_when_valid(): void
    {
        [$cot, $token] = $this->makeSentQuotation('tok-ok', false);

        $resp = $this->apiJson('GET', "/api/client/quotations/{$cot->id}?token={$token}")
            ->assertOk();

        $resp->assertJsonStructure([
            'data' => [
                'id',
                'folio',
                'status',
                'subtotal',
                'iva',
                'total',
                'items',
            ],
        ]);

        $this->assertSame($cot->id, (int)$resp->json('data.id'));
        $this->assertSame(Cotizacion::ESTATUS_SENT, (string)$resp->json('data.status'));

        // Items y servicios
        $this->assertIsArray($resp->json('data.items'));
        $this->assertGreaterThan(0, count($resp->json('data.items')));
        $this->assertIsArray($resp->json('data.items.0.services'));
    }

    public function test_client_api_approve_requires_sent_and_not_expired_and_invalidates_token(): void
    {
        [$cot, $token] = $this->makeSentQuotation('tok-approve', false);

        $this->apiJson('POST', "/api/client/quotations/{$cot->id}/approve?token={$token}")
            ->assertOk()
            ->assertJsonPath('data.status', Cotizacion::ESTATUS_APPROVED)
            ->assertJsonPath('solicitudes_generadas', 1);

        $cot->refresh();
        $this->assertSame(Cotizacion::ESTATUS_APPROVED, $cot->estatus);
        $this->assertNotNull($cot->approved_at);
        $this->assertNull($cot->approval_token_hash);

        $this->assertSame(1, (int)\DB::table('cotizacion_audit_logs')->where('cotizacion_id', (int)$cot->id)->where('action', 'approved')->count());

        // Se creó 1 solicitud por cada línea de servicio (en este fixture es 1)
        $this->assertSame(1, Solicitud::where('id_cotizacion', (int)$cot->id)->count());
        $sol = Solicitud::where('id_cotizacion', (int)$cot->id)->first();
        $this->assertNotNull($sol);
        $this->assertSame((int)$cot->id, (int)$sol->id_cotizacion);
        $this->assertNotNull($sol->id_cotizacion_item_servicio);
        $this->assertNotNull($sol->id_cotizacion_item);

        // Idempotencia: re-disparar el evento no debe duplicar solicitudes
        event(new \App\Events\QuotationApproved((int)$cot->id, (int)$cot->id_cliente));
        $this->assertSame(1, Solicitud::where('id_cotizacion', (int)$cot->id)->count());

        // Reusar el mismo token después de aprobar => token inválido (ya se invalidó)
        $this->apiJson('GET', "/api/client/quotations/{$cot->id}?token={$token}")
            ->assertStatus(401)
            ->assertJsonPath('code', 'TOKEN_INVALID');

        // Expirada
        [$cot2, $token2] = $this->makeSentQuotation('tok-exp', true);
        $this->apiJson('POST', "/api/client/quotations/{$cot2->id}/approve?token={$token2}")
            ->assertStatus(410)
            ->assertJsonPath('code', 'QUOTATION_EXPIRED');
    }

    public function test_client_api_reject_allows_optional_reason_and_invalidates_token(): void
    {
        [$cot, $token] = $this->makeSentQuotation('tok-reject', false);

        $this->apiJson('POST', "/api/client/quotations/{$cot->id}/reject?token={$token}", [
            'motivo' => 'No procede',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Cotizacion::ESTATUS_REJECTED);

        $cot->refresh();
        $this->assertSame(Cotizacion::ESTATUS_REJECTED, $cot->estatus);
        $this->assertNotNull($cot->rejected_at);
        $this->assertSame('No procede', (string)$cot->motivo_rechazo);
        $this->assertNull($cot->approval_token_hash);

        $this->assertSame(1, (int)\DB::table('cotizacion_audit_logs')->where('cotizacion_id', (int)$cot->id)->where('action', 'rejected')->count());
    }

    public function test_client_api_token_is_scoped_to_the_specific_quotation(): void
    {
        [$cotA, $tokenA] = $this->makeSentQuotation('tok-A', false);
        [$cotB, $tokenB] = $this->makeSentQuotation('tok-B', false);

        // Token A no debe funcionar para cotización B
        $this->apiJson('GET', "/api/client/quotations/{$cotB->id}?token={$tokenA}")
            ->assertStatus(401)
            ->assertJsonPath('code', 'TOKEN_INVALID');

        // Token B sí debe funcionar para cotización B
        $this->apiJson('GET', "/api/client/quotations/{$cotB->id}?token={$tokenB}")
            ->assertOk()
            ->assertJsonPath('data.id', (int)$cotB->id);

        // Token A no debe permitir aprobar cotización B
        $this->apiJson('POST', "/api/client/quotations/{$cotB->id}/approve?token={$tokenA}")
            ->assertStatus(401)
            ->assertJsonPath('code', 'TOKEN_INVALID');

        // Token B permite aprobar cotización B
        $this->apiJson('POST', "/api/client/quotations/{$cotB->id}/approve?token={$tokenB}")
            ->assertOk()
            ->assertJsonPath('data.status', Cotizacion::ESTATUS_APPROVED);
    }
}
