<?php

namespace Tests\Feature\Web;

use App\Models\CentroCosto;
use App\Models\CentroTrabajo;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\User;
use App\Notifications\QuotationSentDatabaseNotification;
use App\Notifications\QuotationSentNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CotizacionesSendRecipientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // En este proyecto APP_URL suele traer subpath (/UPPER_CONTROL/public) por XAMPP.
        // En tests eso rompe el enrutado porque las requests se vuelven /UPPER_CONTROL/public/...
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');

        // Migraciones del proyecto fallan en sqlite (MySQL-only). Usamos schema mínimo.
        $this->useSqliteFileDatabase();
        $this->setUpMinimalSchema();

        // Evitar shared props pesados.
        $this->withoutMiddleware(\App\Http\Middleware\HandleInertiaRequests::class);

        // Roles base.
        Role::firstOrCreate(['name' => 'coordinador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Cliente_Supervisor', 'guard_name' => 'web']);

        // Este proyecto registra Gate::before vía Spatie y consulta permisos por nombre.
        // Si el permiso no existe, Spatie lanza excepción (500). Sembramos el mínimo.
        Permission::firstOrCreate(['name' => 'send', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function useSqliteFileDatabase(): void
    {
        $dbPath = database_path('testing-send.sqlite');
        if (!file_exists($dbPath)) {
            @touch($dbPath);
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $dbPath,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
    }

    private function setUpMinimalSchema(): void
    {
        Schema::disableForeignKeyConstraints();

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

        // spatie/permission mínimo (para middleware role + assignRole)
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

        if (!Schema::hasTable('cotizaciones')) {
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
                $t->string('estatus', 20)->default(Cotizacion::ESTATUS_DRAFT);
                $t->timestamp('sent_at')->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->string('approval_token_hash', 64)->nullable();
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

        if (!Schema::hasTable('client_contacts')) {
            Schema::create('client_contacts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('client_id');
                $t->string('name')->nullable();
                $t->string('email');
                $t->boolean('is_primary')->default(false);
                $t->timestamps();
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function test_send_with_selected_recipient_sends_db_notification_to_client_and_mail_on_demand(): void
    {
        Notification::fake();

        // Sanity: el routing base está cargado.
        $this->get('/up')->assertOk();

        $centro = CentroTrabajo::create(['nombre' => 'Centro 1', 'prefijo' => 'C1']);
        $cc = CentroCosto::create(['id_centrotrabajo' => $centro->id, 'nombre' => 'CC 1', 'activo' => true]);

        $client = User::create([
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
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

        $cot = Cotizacion::create([
            'folio' => 'C1-COT-TEST-0001',
            'created_by' => $coord->id,
            'id_cliente' => $client->id,
            'id_centrotrabajo' => $centro->id,
            'id_centrocosto' => $cc->id,
            'estatus' => Cotizacion::ESTATUS_DRAFT,
            'subtotal' => 100,
            'iva' => 16,
            'total' => 116,
        ]);

        CotizacionItem::create([
            'cotizacion_id' => $cot->id,
            'descripcion' => 'Item 1',
            'cantidad' => 1,
        ]);

        $contactEmail = 'contacto@example.com';
        DB::table('client_contacts')->insert([
            'client_id' => $client->id,
            'name' => 'Contacto',
            'email' => $contactEmail,
            'is_primary' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($coord);

        $this->post("/cotizaciones/{$cot->id}/send", [
            'recipient_email' => $contactEmail,
            'expires_at' => now()->addDays(5)->toDateString(),
        ])->assertStatus(302);

        $cot->refresh();
        $this->assertSame(Cotizacion::ESTATUS_SENT, $cot->estatus);
        $this->assertNotNull($cot->approval_token_hash);

        $log = DB::table('cotizacion_audit_logs')
            ->where('cotizacion_id', (int)$cot->id)
            ->where('action', 'sent')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($log);
        $payload = json_decode((string)($log->payload ?? 'null'), true);
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('token', $payload);
        $this->assertSame('contacto@example.com', (string)($payload['recipient_email'] ?? ''));

        Notification::assertSentTo($client, QuotationSentDatabaseNotification::class, function ($notification, $channels) use ($cot) {
            $this->assertSame(['database'], $channels);
            $payload = $notification->toDatabase($cot->cliente);
            $this->assertArrayNotHasKey('token', $payload);
            $this->assertSame($cot->id, (int)($payload['quotation_id'] ?? 0));
            return true;
        });

        Notification::assertSentOnDemand(QuotationSentNotification::class, function ($notification, $channels, $notifiable) use ($contactEmail, $cot) {
            $this->assertSame(['mail'], $channels);
            $this->assertSame(strtolower($contactEmail), strtolower((string)($notifiable->routes['mail'] ?? '')));

            $cot->refresh();
            $this->assertSame(hash('sha256', (string)$notification->plainToken), (string)$cot->approval_token_hash);
            return true;
        });
    }

    public function test_send_rejects_recipient_email_not_in_allowed_list(): void
    {
        Notification::fake();

        // Sanity: el routing base está cargado.
        $this->get('/up')->assertOk();

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
            'email' => 'coord2@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $coord->assignRole('coordinador');

        $cot = Cotizacion::create([
            'folio' => 'C1-COT-TEST-0002',
            'created_by' => $coord->id,
            'id_cliente' => $client->id,
            'id_centrotrabajo' => $centro->id,
            'id_centrocosto' => $cc->id,
            'estatus' => Cotizacion::ESTATUS_DRAFT,
            'subtotal' => 0,
            'iva' => 0,
            'total' => 0,
        ]);

        $this->actingAs($coord);

        $this->from("/cotizaciones/{$cot->id}")
            ->post("/cotizaciones/{$cot->id}/send", [
                'recipient_email' => 'intruso@example.com',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['recipient_email']);

        $cot->refresh();
        $this->assertSame(Cotizacion::ESTATUS_DRAFT, $cot->estatus);
        Notification::assertNothingSent();
    }

    public function test_recipients_endpoint_returns_allowed_emails_with_meta_for_coordinator(): void
    {
        $centro = CentroTrabajo::create(['nombre' => 'Centro 1', 'prefijo' => 'C1']);
        $cc = CentroCosto::create(['id_centrotrabajo' => $centro->id, 'nombre' => 'CC 1', 'activo' => true]);

        $client = User::create([
            'name' => 'Cliente',
            'email' => 'Cliente@Example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $client->assignRole('Cliente_Supervisor');

        $coord = User::create([
            'name' => 'Coord',
            'email' => 'coord3@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $coord->assignRole('coordinador');

        $cot = Cotizacion::create([
            'folio' => 'C1-COT-TEST-0003',
            'created_by' => $coord->id,
            'id_cliente' => $client->id,
            'id_centrotrabajo' => $centro->id,
            'id_centrocosto' => $cc->id,
            'estatus' => Cotizacion::ESTATUS_DRAFT,
            'subtotal' => 0,
            'iva' => 0,
            'total' => 0,
        ]);

        DB::table('client_contacts')->insert([
            'client_id' => $client->id,
            'name' => 'Contacto Principal',
            'email' => 'contacto@example.com',
            'is_primary' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($coord);

        $resp = $this->getJson("/cotizaciones/{$cot->id}/recipients")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['email', 'label', 'is_primary'],
                ],
            ]);

        $rows = $resp->json('data');
        $this->assertIsArray($rows);

        $emails = array_map(fn ($r) => strtolower((string)($r['email'] ?? '')), $rows);
        $this->assertContains('cliente@example.com', $emails);
        $this->assertContains('contacto@example.com', $emails);

        $contactRow = collect($rows)->firstWhere('email', 'contacto@example.com');
        $this->assertNotNull($contactRow);
        $this->assertTrue((bool)($contactRow['is_primary'] ?? false));
        $this->assertStringContainsString('Contacto Principal', (string)($contactRow['label'] ?? ''));
        $this->assertStringContainsString('principal', strtolower((string)($contactRow['label'] ?? '')));
    }

    public function test_recipients_endpoint_is_forbidden_for_client_role(): void
    {
        $centro = CentroTrabajo::create(['nombre' => 'Centro 1', 'prefijo' => 'C1']);
        $cc = CentroCosto::create(['id_centrotrabajo' => $centro->id, 'nombre' => 'CC 1', 'activo' => true]);

        $client = User::create([
            'name' => 'Cliente',
            'email' => 'cliente4@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $client->assignRole('Cliente_Supervisor');

        $coord = User::create([
            'name' => 'Coord',
            'email' => 'coord4@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro->id,
        ]);
        $coord->assignRole('coordinador');

        $cot = Cotizacion::create([
            'folio' => 'C1-COT-TEST-0004',
            'created_by' => $coord->id,
            'id_cliente' => $client->id,
            'id_centrotrabajo' => $centro->id,
            'id_centrocosto' => $cc->id,
            'estatus' => Cotizacion::ESTATUS_DRAFT,
            'subtotal' => 0,
            'iva' => 0,
            'total' => 0,
        ]);

        $this->actingAs($client);
        $this->getJson("/cotizaciones/{$cot->id}/recipients")
            ->assertForbidden();
    }

    public function test_recipients_endpoint_is_forbidden_for_coordinator_not_owner_or_wrong_center(): void
    {
        $centro1 = CentroTrabajo::create(['nombre' => 'Centro 1', 'prefijo' => 'C1']);
        $centro2 = CentroTrabajo::create(['nombre' => 'Centro 2', 'prefijo' => 'C2']);

        $cc = CentroCosto::create(['id_centrotrabajo' => $centro1->id, 'nombre' => 'CC 1', 'activo' => true]);

        $client = User::create([
            'name' => 'Cliente',
            'email' => 'cliente5@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro1->id,
        ]);
        $client->assignRole('Cliente_Supervisor');

        $ownerCoord = User::create([
            'name' => 'Coord Owner',
            'email' => 'coord-owner@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro1->id,
        ]);
        $ownerCoord->assignRole('coordinador');

        $otherCoord = User::create([
            'name' => 'Coord Other',
            'email' => 'coord-other@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro2->id,
        ]);
        $otherCoord->assignRole('coordinador');

        $cot = Cotizacion::create([
            'folio' => 'C1-COT-TEST-0005',
            'created_by' => $ownerCoord->id,
            'id_cliente' => $client->id,
            'id_centrotrabajo' => $centro1->id,
            'id_centrocosto' => $cc->id,
            'estatus' => Cotizacion::ESTATUS_DRAFT,
            'subtotal' => 0,
            'iva' => 0,
            'total' => 0,
        ]);

        $this->actingAs($otherCoord);
        $this->getJson("/cotizaciones/{$cot->id}/recipients")
            ->assertForbidden();
    }

    public function test_send_is_forbidden_for_coordinator_not_owner_or_wrong_center(): void
    {
        Notification::fake();

        $centro1 = CentroTrabajo::create(['nombre' => 'Centro 1', 'prefijo' => 'C1']);
        $centro2 = CentroTrabajo::create(['nombre' => 'Centro 2', 'prefijo' => 'C2']);
        $cc = CentroCosto::create(['id_centrotrabajo' => $centro1->id, 'nombre' => 'CC 1', 'activo' => true]);

        $client = User::create([
            'name' => 'Cliente',
            'email' => 'cliente6@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro1->id,
        ]);
        $client->assignRole('Cliente_Supervisor');

        $ownerCoord = User::create([
            'name' => 'Coord Owner',
            'email' => 'coord-owner2@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro1->id,
        ]);
        $ownerCoord->assignRole('coordinador');

        $otherCoord = User::create([
            'name' => 'Coord Other',
            'email' => 'coord-other2@example.com',
            'password' => null,
            'centro_trabajo_id' => $centro2->id,
        ]);
        $otherCoord->assignRole('coordinador');

        $cot = Cotizacion::create([
            'folio' => 'C1-COT-TEST-0006',
            'created_by' => $ownerCoord->id,
            'id_cliente' => $client->id,
            'id_centrotrabajo' => $centro1->id,
            'id_centrocosto' => $cc->id,
            'estatus' => Cotizacion::ESTATUS_DRAFT,
            'subtotal' => 0,
            'iva' => 0,
            'total' => 0,
        ]);

        $this->actingAs($otherCoord);

        $this->post("/cotizaciones/{$cot->id}/send", [
            'recipient_email' => 'cliente6@example.com',
        ])->assertForbidden();

        $cot->refresh();
        $this->assertSame(Cotizacion::ESTATUS_DRAFT, $cot->estatus);
        $this->assertNull($cot->approval_token_hash);

        Notification::assertNothingSent();
    }
}
