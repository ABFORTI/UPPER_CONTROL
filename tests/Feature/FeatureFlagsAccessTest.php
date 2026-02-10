<?php

namespace Tests\Feature;

use App\Models\CentroTrabajo;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeatureFlagsAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Este test usa un esquema mínimo; desactivamos middlewares globales
        // que consultan tablas que aquí no existen.
        $this->withoutMiddleware([
            \App\Http\Middleware\HandleInertiaRequests::class,
            VerifyCsrfToken::class,
        ]);

        // Mantener tests independientes de las migraciones reales (algunas son MySQL-only).
        $this->useSqliteFileDatabase();
        $this->setUpMinimalSchema();
    }

    private function useSqliteFileDatabase(): void
    {
        $dbPath = database_path('testing-featureflags.sqlite');
        if (!file_exists($dbPath)) {
            @touch($dbPath);
        }

        config([
            'app.url' => 'http://localhost',
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

        // activitylog (User usa LogsActivity y escribe al crear/actualizar)
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

        // Centros
        Schema::create('centros_trabajo', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('numero_centro')->nullable();
            $t->string('prefijo')->nullable();
            $t->string('direccion')->nullable();
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        // Users
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

        // spatie/permission mínimo para hasRole('admin')
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->index(['model_id', 'model_type']);
        });

        // Features y pivote
        Schema::create('features', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('nombre');
            $t->text('descripcion')->nullable();
            $t->timestamps();
        });

        Schema::create('centro_feature', function (Blueprint $t) {
            $t->unsignedBigInteger('centro_trabajo_id');
            $t->unsignedBigInteger('feature_id');
            $t->boolean('enabled')->default(false);
            $t->primary(['centro_trabajo_id', 'feature_id']);
        });
    }

    public function test_bloquea_con_403_si_centro_no_tiene_feature_habilitada(): void
    {
        $centro = CentroTrabajo::create(['nombre' => 'Centro A']);
        $user = User::create([
            'name' => 'User',
            'email' => 'user@test.com',
            'centro_trabajo_id' => $centro->id,
        ]);

        Feature::create([
            'key' => 'subir_excel',
            'nombre' => 'Subir solicitudes por Excel',
        ]);

        $this->actingAs($user);

        // La ruta real está protegida con ->middleware('feature:subir_excel')
        $this->postJson('/solicitudes/parse-excel', [])->assertStatus(403);
    }

    public function test_permite_acceso_si_feature_esta_habilitada_en_pivote(): void
    {
        $centro = CentroTrabajo::create(['nombre' => 'Centro A']);
        $user = User::create([
            'name' => 'User',
            'email' => 'user2@test.com',
            'centro_trabajo_id' => $centro->id,
        ]);

        $feature = Feature::create([
            'key' => 'subir_excel',
            'nombre' => 'Subir solicitudes por Excel',
        ]);

        DB::table('centro_feature')->insert([
            'centro_trabajo_id' => $centro->id,
            'feature_id' => $feature->id,
            'enabled' => 1,
        ]);

        $this->actingAs($user);

        // Pasa middleware y falla validación por no enviar archivo
        $this->postJson('/solicitudes/parse-excel', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');
    }

    public function test_admin_bypassea_middleware_aun_sin_feature(): void
    {
        $centro = CentroTrabajo::create(['nombre' => 'Centro A']);
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'centro_trabajo_id' => $centro->id,
        ]);

        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        Feature::create([
            'key' => 'subir_excel',
            'nombre' => 'Subir solicitudes por Excel',
        ]);

        $this->actingAs($admin);

        // Admin no requiere pivote habilitado; debe llegar a validación
        $this->postJson('/solicitudes/parse-excel', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');
    }
}
