<?php

namespace Tests\Feature;

use App\Models\CentroTrabajo;
use App\Models\ServicioCentro;
use App\Models\ServicioEmpresa;
use App\Models\ServicioTamano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiciosPorCentroIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private CentroTrabajo $centroA;
    private CentroTrabajo $centroB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->centroA = CentroTrabajo::create([
            'nombre' => 'Centro A',
            'numero_centro' => '100',
            'prefijo' => 'CA',
            'activo' => true,
        ]);

        $this->centroB = CentroTrabajo::create([
            'nombre' => 'Centro B',
            'numero_centro' => '200',
            'prefijo' => 'CB',
            'activo' => true,
        ]);
    }

    public function test_permite_mismo_nombre_en_centros_distintos_con_configuracion_distinta(): void
    {
        $this->actingAs($this->admin)
            ->post(route('servicios.crear'), [
                'nombre' => 'Almacenaje',
                'id_centro' => $this->centroA->id,
                'usa_tamanos' => false,
                'precio_base' => 123.45,
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('servicios.crear'), [
                'nombre' => 'Almacenaje',
                'id_centro' => $this->centroB->id,
                'usa_tamanos' => true,
                'tamanos' => [
                    'chico' => 10,
                    'mediano' => 20,
                    'grande' => 30,
                    'jumbo' => 40,
                ],
            ])
            ->assertRedirect();

        $this->assertEquals(2, ServicioEmpresa::where('nombre', 'Almacenaje')->count());

        $a = ServicioCentro::where('id_centrotrabajo', $this->centroA->id)->where('nombre', 'Almacenaje')->firstOrFail();
        $b = ServicioCentro::where('id_centrotrabajo', $this->centroB->id)->where('nombre', 'Almacenaje')->firstOrFail();

        $this->assertFalse((bool) $a->usa_tamanos);
        $this->assertTrue((bool) $b->usa_tamanos);
        $this->assertEquals(123.45, (float) $a->precio_base);
        $this->assertEquals(4, ServicioTamano::where('id_servicio_centro', $b->id)->count());
    }

    public function test_bloquea_duplicado_del_mismo_nombre_en_mismo_centro(): void
    {
        $payload = [
            'nombre' => 'Distribucion',
            'id_centro' => $this->centroA->id,
            'usa_tamanos' => true,
            'tamanos' => [
                'chico' => 1,
                'mediano' => 2,
                'grande' => 3,
                'jumbo' => 4,
            ],
        ];

        $this->actingAs($this->admin)->post(route('servicios.crear'), $payload)->assertRedirect();

        $this->actingAs($this->admin)
            ->from(route('servicios.index', ['centro' => $this->centroA->id]))
            ->post(route('servicios.crear'), $payload)
            ->assertRedirect(route('servicios.index', ['centro' => $this->centroA->id]));

        $this->assertEquals(1, ServicioCentro::where('id_centrotrabajo', $this->centroA->id)->where('nombre', 'Distribucion')->count());
    }

    public function test_distribucion_puede_existir_por_tamanos_en_a_y_unitario_en_b(): void
    {
        $this->actingAs($this->admin)->post(route('servicios.crear'), [
            'nombre' => 'Distribucion',
            'id_centro' => $this->centroA->id,
            'usa_tamanos' => true,
            'tamanos' => [
                'chico' => 21,
                'mediano' => 22,
                'grande' => 23,
                'jumbo' => 24,
            ],
        ])->assertRedirect();

        $this->actingAs($this->admin)->post(route('servicios.crear'), [
            'nombre' => 'Distribucion',
            'id_centro' => $this->centroB->id,
            'usa_tamanos' => false,
            'precio_base' => 88,
        ])->assertRedirect();

        $a = ServicioCentro::where('id_centrotrabajo', $this->centroA->id)->where('nombre', 'Distribucion')->firstOrFail();
        $b = ServicioCentro::where('id_centrotrabajo', $this->centroB->id)->where('nombre', 'Distribucion')->firstOrFail();

        $this->assertTrue((bool) $a->usa_tamanos);
        $this->assertFalse((bool) $b->usa_tamanos);
        $this->assertEquals(4, ServicioTamano::where('id_servicio_centro', $a->id)->count());
        $this->assertEquals(88.0, (float) $b->precio_base);
    }

    public function test_editar_servicio_en_un_centro_no_afecta_otro_centro(): void
    {
        $this->actingAs($this->admin)->post(route('servicios.crear'), [
            'nombre' => 'Almacenaje',
            'id_centro' => $this->centroA->id,
            'usa_tamanos' => false,
            'precio_base' => 11,
        ]);

        $this->actingAs($this->admin)->post(route('servicios.crear'), [
            'nombre' => 'Almacenaje',
            'id_centro' => $this->centroB->id,
            'usa_tamanos' => true,
            'tamanos' => [
                'chico' => 100,
                'mediano' => 200,
                'grande' => 300,
                'jumbo' => 400,
            ],
        ]);

        $servicioB = ServicioCentro::where('id_centrotrabajo', $this->centroB->id)
            ->where('nombre', 'Almacenaje')
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('servicios.guardar'), [
                'id_centro' => $this->centroB->id,
                'items' => [
                    [
                        'id_servicio' => $servicioB->id_servicio,
                        'usa_tamanos' => true,
                        'tamanos' => [
                            'chico' => 111,
                            'mediano' => 222,
                            'grande' => 333,
                            'jumbo' => 444,
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $a = ServicioCentro::where('id_centrotrabajo', $this->centroA->id)->where('nombre', 'Almacenaje')->firstOrFail();
        $b = ServicioCentro::with('tamanos')->where('id_centrotrabajo', $this->centroB->id)->where('nombre', 'Almacenaje')->firstOrFail();

        $this->assertFalse((bool) $a->usa_tamanos);
        $this->assertEquals(11.0, (float) $a->precio_base);

        $this->assertTrue((bool) $b->usa_tamanos);
        $this->assertEquals(111.0, (float) optional($b->tamanos->firstWhere('tamano', 'chico'))->precio);
    }

    public function test_listado_muestra_datos_reales_por_centro(): void
    {
        $this->actingAs($this->admin)->post(route('servicios.crear'), [
            'nombre' => 'Distribucion',
            'id_centro' => $this->centroA->id,
            'usa_tamanos' => false,
            'precio_base' => 55,
        ]);

        $this->actingAs($this->admin)->post(route('servicios.crear'), [
            'nombre' => 'Distribucion',
            'id_centro' => $this->centroB->id,
            'usa_tamanos' => true,
            'tamanos' => [
                'chico' => 5,
                'mediano' => 6,
                'grande' => 7,
                'jumbo' => 8,
            ],
        ]);

        $responseA = $this->actingAs($this->admin)->get(route('servicios.index', ['centro' => $this->centroA->id]));
        $responseA->assertOk();
        $pageA = $responseA->viewData('page');
        $rowsA = collect(data_get($pageA, 'props.rows', []));

        $responseB = $this->actingAs($this->admin)->get(route('servicios.index', ['centro' => $this->centroB->id]));
        $responseB->assertOk();
        $pageB = $responseB->viewData('page');
        $rowsB = collect(data_get($pageB, 'props.rows', []));

        $rowA = $rowsA->firstWhere('servicio', 'Distribucion');
        $rowB = $rowsB->firstWhere('servicio', 'Distribucion');

        $this->assertNotNull($rowA);
        $this->assertNotNull($rowB);
        $this->assertFalse((bool) data_get($rowA, 'usa_tamanos'));
        $this->assertTrue((bool) data_get($rowB, 'usa_tamanos'));
    }
}
