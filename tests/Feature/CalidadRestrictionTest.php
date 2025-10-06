<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class CalidadRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Asegura que el rol exista
        Role::firstOrCreate(['name' => 'calidad']);
    }

    public function test_dashboard_redirects_for_only_calidad_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('calidad');

        $resp = $this->actingAs($user)->get(route('dashboard'));
        $resp->assertRedirect(route('calidad.index'));
    }

    public function test_calidad_index_accessible_for_only_calidad_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('calidad');

        $resp = $this->actingAs($user)->get(route('calidad.index'));
        $resp->assertStatus(200);
    }
}
