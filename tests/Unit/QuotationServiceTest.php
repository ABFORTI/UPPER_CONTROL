<?php

namespace Tests\Unit;

use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\CotizacionItemServicio;
use App\Models\ServicioEmpresa;
use App\Models\User;
use App\Services\QuotationService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuotationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Este proyecto tiene migraciones MySQL-only (ALTER TABLE ... MODIFY/ENUM) que fallan en
        // sqlite :memory: durante tests. Para estas pruebas unitarias creamos un schema mínimo.
        $this->createMinimalSchema();
    }

    private function createMinimalSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('phone')->nullable();
            $t->unsignedBigInteger('centro_trabajo_id')->nullable();
            $t->boolean('activo')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $t) {
            $t->id();
            $t->string('log_name')->nullable();
            $t->text('description');
            $t->nullableMorphs('subject');
            $t->nullableMorphs('causer');
            $t->json('properties')->nullable();
            $t->string('event')->nullable();
            $t->uuid('batch_uuid')->nullable();
            $t->timestamps();
        });

        Schema::create('centros_trabajo', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('prefijo')->unique();
            $t->timestamps();
        });

        Schema::create('centros_costos', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_centrotrabajo');
            $t->string('nombre');
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        Schema::create('servicios_empresa', function (Blueprint $t) {
            $t->id();
            $t->string('nombre')->unique();
            $t->boolean('usa_tamanos')->default(false);
            $t->timestamps();
        });

        Schema::create('cotizaciones', function (Blueprint $t) {
            $t->id();
            $t->string('folio')->unique();
            $t->unsignedBigInteger('created_by');
            $t->unsignedBigInteger('id_cliente');
            $t->unsignedBigInteger('id_centrotrabajo');
            $t->unsignedBigInteger('id_centrocosto');
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('iva', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->decimal('tax', 12, 2)->nullable();
            $t->string('estatus', 20)->default('draft');
            $t->string('approval_token_hash', 64)->nullable();
            $t->timestamps();
        });

        Schema::create('cotizacion_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('cotizacion_id');
            $t->string('descripcion');
            $t->unsignedInteger('cantidad')->default(1);
            $t->text('notas')->nullable();
            $t->timestamps();
        });

        Schema::create('cotizacion_item_servicios', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('cotizacion_item_id');
            $t->unsignedBigInteger('id_servicio');
            $t->string('tamano', 20)->nullable();
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

    private function makeBaseCotizacion(): Cotizacion
    {
        $centroId = (int)DB::table('centros_trabajo')->insertGetId(['nombre' => 'Centro 1', 'prefijo' => 'CEN', 'created_at' => now(), 'updated_at' => now()]);
        $ccId = (int)DB::table('centros_costos')->insertGetId(['id_centrotrabajo' => $centroId, 'nombre' => 'CC 1', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $createdBy = User::create([
            'name' => 'Coord',
            'email' => 'coord@example.com',
            'password' => Hash::make('secret'),
        ]);

        $cliente = User::create([
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'password' => Hash::make('secret'),
        ]);

        return Cotizacion::create([
            'folio' => 'COT-2026-0001',
            'created_by' => $createdBy->id,
            'id_cliente' => $cliente->id,
            'id_centrotrabajo' => $centroId,
            'id_centrocosto' => $ccId,
            'estatus' => Cotizacion::ESTATUS_DRAFT,
            'subtotal' => 0,
            'iva' => 0,
            'total' => 0,
        ]);
    }

    public function test_recalculate_totals_updates_lines_and_header(): void
    {
        $cotizacion = $this->makeBaseCotizacion();

        $servicio = ServicioEmpresa::create(['nombre' => 'Servicio 1', 'usa_tamanos' => false]);

        $item = CotizacionItem::create([
            'cotizacion_id' => $cotizacion->id,
            'descripcion' => 'Item 1',
            'cantidad' => 1,
        ]);

        $line = CotizacionItemServicio::create([
            'cotizacion_item_id' => $item->id,
            'id_servicio' => $servicio->id,
            'cantidad' => 2,
            'precio_unitario' => 100,
            'subtotal' => 0,
            'iva' => 0,
            'total' => 0,
        ]);

        $svc = new QuotationService();
        $updated = $svc->recalculateTotals($cotizacion, 0.16);

        $line->refresh();
        $this->assertEquals(200.00, (float)$line->subtotal);
        $this->assertEquals(32.00, (float)$line->iva);
        $this->assertEquals(232.00, (float)$line->total);

        $updated->refresh();
        $this->assertEquals(200.00, (float)$updated->subtotal);
        $this->assertEquals(32.00, (float)$updated->tax);
        $this->assertEquals(32.00, (float)$updated->iva);
        $this->assertEquals(232.00, (float)$updated->total);
    }

    public function test_assert_editable_only_allows_draft(): void
    {
        $cotizacion = $this->makeBaseCotizacion();
        $svc = new QuotationService();

        $svc->assertEditable($cotizacion);

        $cotizacion->update(['estatus' => Cotizacion::ESTATUS_SENT]);

        $this->expectException(DomainException::class);
        $svc->assertEditable($cotizacion);
    }

    public function test_generate_folio_increments(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-25 12:00:00'));

        $svc = new QuotationService();

        $first = $this->makeBaseCotizacion();
        $this->assertEquals('COT-2026-0001', $first->folio);

        $next = $svc->generateFolio('COT');
        $this->assertEquals('COT-2026-0002', $next);
    }

    public function test_generate_approval_token_stores_hash_only(): void
    {
        $cotizacion = $this->makeBaseCotizacion();
        $svc = new QuotationService();

        $token = $svc->generateApprovalToken($cotizacion);
        $this->assertNotEmpty($token);

        $cotizacion->refresh();
        $this->assertEquals(hash('sha256', $token), $cotizacion->approval_token_hash);
        $this->assertTrue($svc->approvalTokenMatches($cotizacion, $token));
        $this->assertFalse($svc->approvalTokenMatches($cotizacion, 'otro-token'));
    }
}
