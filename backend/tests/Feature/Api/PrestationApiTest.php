<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Prestation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrestationApiTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function prestation(array $overrides = []): Prestation
    {
        return Prestation::create(array_merge([
            'code' => 'ACMPT',
            'designation' => 'Assistance comptable',
            'tarif_initial' => 120000,
            'duree_mois' => 12,
            'actif' => true,
        ], $overrides));
    }

    public function test_admin_liste_les_prestations(): void
    {
        $this->prestation();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/prestations')
            ->assertOk()
            ->assertJsonFragment(['code' => 'ACMPT']);
    }

    public function test_admin_cree_une_prestation(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/prestations', [
                'code' => 'AUDIT',
                'designation' => 'Audit legal',
                'tarif_initial' => 200000,
                'duree_mois' => 6,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'AUDIT');

        $this->assertDatabaseHas('prestations', ['code' => 'AUDIT']);
    }

    public function test_creation_prestation_exige_les_champs_obligatoires(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/prestations', ['designation' => 'Sans code'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'tarif_initial', 'duree_mois']);
    }
}
