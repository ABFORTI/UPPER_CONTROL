<?php

namespace Tests\Feature;

use App\Models\Orden;
use App\Models\User;
use App\Models\Solicitud;
use App\Models\CentroTrabajo;
use App\Models\CentroCosto;
use App\Models\Marca;
use App\Models\ServicioEmpresa;
use App\Models\Area;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrdenExcelUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Orden $orden;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        
        // Crear rol admin
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        
        // Crear usuario admin
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
        ]);
        $this->admin->assignRole('admin');
        
        // Crear centro de trabajo
        $centro = CentroTrabajo::create([
            'codigo' => 'CEDIM',
            'nombre' => 'INGCEDIM',
            'prefijo' => 'CED',
            'direccion' => 'Test',
            'activo' => true,
        ]);
        
        // Crear solicitud
        $solicitud = Solicitud::create([
            'folio' => 'SOL-TEST-001',
            'id_centrotrabajo' => $centro->id,
            'id_solicitante' => $this->admin->id,
            'descripcion' => 'Test',
            'estatus' => 'autorizada',
            'id_centrocosto' => 1,
        ]);
        
        // Crear Orden de prueba
        $this->orden = Orden::create([
            'id_solicitud' => $solicitud->id,
            'id_centrotrabajo' => $centro->id,
            'id_servicio' => null,
            'estatus' => 'generada',
        ]);
        
        // Crear datos de catálogo para mapeo
        CentroTrabajo::create([
            'codigo' => 'CEDIM2',
            'nombre' => 'Centro Test',
            'prefijo' => 'CT',
            'direccion' => 'Test',
            'activo' => true,
        ]);
        
        CentroCosto::create([
            'codigo' => 'KPI01',
            'nombre' => 'KPI 01',
            'id_centrotrabajo' => $centro->id,
            'activo' => true,
        ]);
        
        Marca::create([
            'nombre' => 'COPPEL',
            'activo' => true,
        ]);
        
        ServicioEmpresa::create([
            'codigo' => 'ALM01',
            'nombre' => 'Almacenaje',
            'activo' => true,
        ]);
        
        Area::create([
            'nombre' => 'INSUMOS',
            'activo' => true,
        ]);
    }

    /** @test */
    public function usuario_autorizado_puede_subir_archivo_excel()
    {
        $this->actingAs($this->admin);
        
        // Crear un archivo Excel falso
        $file = UploadedFile::fake()->create('test.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        
        $response = $this->postJson(route('ordenes.archivo.upload', $this->orden->id), [
            'excel' => $file
        ]);
        
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ]);
        
        // Verificar que el archivo se guardó en storage
        $this->assertDatabaseHas('ordenes_trabajo', [
            'id' => $this->orden->id,
        ]);
        
        // Verificar que se actualizaron los campos
        $this->orden->refresh();
        $this->assertNotNull($this->orden->archivo_excel_path);
        $this->assertNotNull($this->orden->archivo_excel_nombre_original);
        $this->assertEquals($this->admin->id, $this->orden->archivo_excel_subido_por);
    }

    /** @test */
    public function no_se_puede_subir_archivo_sin_autenticacion()
    {
        $file = UploadedFile::fake()->create('test.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        
        $response = $this->postJson(route('ordenes.archivo.upload', $this->orden->id), [
            'excel' => $file
        ]);
        
        $response->assertStatus(401);
    }

    /** @test */
    public function debe_ser_archivo_excel_valido()
    {
        $this->actingAs($this->admin);
        
        // Intentar subir un PDF
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');
        
        $response = $this->postJson(route('ordenes.archivo.upload', $this->orden->id), [
            'excel' => $file
        ]);
        
        $response->assertStatus(422)
                 ->assertJsonValidationErrors('excel');
    }

    /** @test */
    public function archivo_no_puede_superar_10mb()
    {
        $this->actingAs($this->admin);
        
        // Archivo de 11MB
        $file = UploadedFile::fake()->create('test.xlsx', 11000, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        
        $response = $this->postJson(route('ordenes.archivo.upload', $this->orden->id), [
            'excel' => $file
        ]);
        
        $response->assertStatus(422)
                 ->assertJsonValidationErrors('excel');
    }

    /** @test */
    public function subir_nuevo_archivo_reemplaza_el_anterior()
    {
        $this->actingAs($this->admin);
        
        // Subir primer archivo
        $file1 = UploadedFile::fake()->create('test1.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->postJson(route('ordenes.archivo.upload', $this->orden->id), [
            'excel' => $file1
        ]);
        
        $this->orden->refresh();
        $firstPath = $this->orden->archivo_excel_path;
        
        // Subir segundo archivo
        $file2 = UploadedFile::fake()->create('test2.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->postJson(route('ordenes.archivo.upload', $this->orden->id), [
            'excel' => $file2
        ]);
        
        $this->orden->refresh();
        $secondPath = $this->orden->archivo_excel_path;
        
        // Verificar que el path cambió
        $this->assertNotEquals($firstPath, $secondPath);
        
        // Verificar que el archivo anterior fue eliminado
        Storage::disk('public')->assertMissing($firstPath);
    }

    /** @test */
    public function usuario_autorizado_puede_descargar_archivo()
    {
        $this->actingAs($this->admin);
        
        // Crear archivo en storage
        Storage::disk('public')->put('ot_archivos/test.xlsx', 'contenido');
        
        // Actualizar orden con info del archivo
        $this->orden->update([
            'archivo_excel_path' => 'ot_archivos/test.xlsx',
            'archivo_excel_nombre_original' => 'test.xlsx',
            'archivo_excel_subido_por' => $this->admin->id,
        ]);
        
        $response = $this->get(route('ordenes.archivo.download', $this->orden->id));
        
        $response->assertStatus(200)
                 ->assertDownload('test.xlsx');
    }

    /** @test */
    public function no_se_puede_descargar_archivo_inexistente()
    {
        $this->actingAs($this->admin);
        
        $response = $this->get(route('ordenes.archivo.download', $this->orden->id));
        
        $response->assertStatus(404);
    }
}
