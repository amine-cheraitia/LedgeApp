<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HealthEndpointAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);
    }

    public function test_health_detaille_refuse_aux_visiteurs_non_authentifies(): void
    {
        $this->getJson('/health')->assertForbidden();
        $this->getJson('/health/dashboard')->assertForbidden();
    }

    public function test_health_detaille_refuse_aux_roles_non_admin(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');

        $this->actingAs($collaborateur)
            ->getJson('/health')
            ->assertForbidden();
    }

    public function test_health_detaille_accessible_a_l_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->getJson('/health')
            ->assertOk();
    }

    public function test_endpoint_up_reste_public(): void
    {
        // Endpoint de monitoring externe (UptimeRobot) : simple, sans diagnostic detaille.
        $this->get('/up')->assertOk();
    }
}
