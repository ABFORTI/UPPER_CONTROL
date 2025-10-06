<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class FacturacionIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'facturacion']);
    }

    public function test_facturas_index_requires_role()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('facturas.index'))->assertStatus(403);
    }

    public function test_facturas_index_access_with_role()
    {
        $user = User::factory()->create();
        $user->assignRole('facturacion');
        $this->actingAs($user)->get(route('facturas.index'))->assertStatus(200);
    }
}
