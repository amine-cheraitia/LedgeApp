<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_liste_les_parametres_du_cabinet(): void
    {
        Setting::create([
            'key' => 'cabinet_nom',
            'value' => 'Cabinet Test',
            'group' => 'general',
            'label' => 'Nom du cabinet',
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonFragment(['key' => 'cabinet_nom', 'value' => 'Cabinet Test']);
    }

    public function test_admin_met_a_jour_les_parametres(): void
    {
        $this->actingAs($this->admin)
            ->putJson('/api/v1/settings', [
                'settings' => [
                    ['key' => 'cabinet_nom', 'value' => 'Nouveau Nom'],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('settings', [
            'key' => 'cabinet_nom',
            'value' => 'Nouveau Nom',
        ]);
    }
}
