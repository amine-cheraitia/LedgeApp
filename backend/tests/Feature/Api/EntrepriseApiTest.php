<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Prestation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EntrepriseApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_can_list_entreprises(): void
    {
        Entreprise::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/entreprises');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_entreprise(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/entreprises', [
                'raison_sociale' => 'Test SARL',
                'regime_fiscal' => 'forfait',
                'categorie' => 'TPE',
                'statut' => 'prospect',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.raison_sociale', 'Test SARL');

        $this->assertDatabaseHas('entreprises', ['raison_sociale' => 'Test SARL']);
    }

    public function test_can_update_entreprise(): void
    {
        $entreprise = Entreprise::factory()->create(['raison_sociale' => 'Avant']);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/entreprises/{$entreprise->id}", [
                'raison_sociale' => 'Apres',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.raison_sociale', 'Apres');
    }

    public function test_update_ignore_le_statut(): void
    {
        // La bascule prospect -> client est reservee a l'Observer (MissionCreated).
        // L'API d'edition ne doit jamais permettre de forcer ce statut a la main.
        $entreprise = Entreprise::factory()->create(['statut' => 'prospect']);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/entreprises/{$entreprise->id}", [
                'raison_sociale' => 'Toujours prospect',
                'statut' => 'client',
            ]);

        $response->assertOk();
        $this->assertSame('prospect', $entreprise->fresh()->statut);
        $this->assertDatabaseHas('entreprises', [
            'id' => $entreprise->id,
            'statut' => 'prospect',
        ]);
    }

    public function test_cannot_delete_entreprise_with_missions(): void
    {
        $exercice = Exercice::create([
            'annee' => (int) date('Y'),
            'date_ouverture' => date('Y').'-01-01',
            'date_cloture' => date('Y').'-12-31',
            'statut' => 'ouvert',
        ]);

        $prestation = Prestation::create([
            'code' => 'TEST',
            'designation' => 'Test',
            'tarif_initial' => 10000,
            'duree_mois' => 12,
            'actif' => true,
        ]);

        $entreprise = Entreprise::factory()->create();
        $entreprise->missions()->create([
            'exercice_id' => $exercice->id,
            'prestation_id' => $prestation->id,
            'reference' => 'M2026-001',
            'prix_ht' => 100000,
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31',
            'statut' => 'en_cours',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/entreprises/{$entreprise->id}");

        $response->assertStatus(409);
    }

    public function test_can_delete_entreprise_without_relations(): void
    {
        $entreprise = Entreprise::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/entreprises/{$entreprise->id}");

        $response->assertOk();
        $this->assertSoftDeleted('entreprises', ['id' => $entreprise->id]);
    }

    public function test_search_filters_entreprises(): void
    {
        Entreprise::factory()->create(['raison_sociale' => 'Alpha Corp']);
        Entreprise::factory()->create(['raison_sociale' => 'Beta Inc']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/entreprises?search=Alpha');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.raison_sociale', 'Alpha Corp');
    }

    public function test_secretaire_peut_creer_entreprise(): void
    {
        $secretaire = User::factory()->create();
        $secretaire->assignRole('secretaire');

        $response = $this->actingAs($secretaire)
            ->postJson('/api/v1/entreprises', [
                'raison_sociale' => 'Sec SARL',
                'regime_fiscal' => 'forfait',
                'categorie' => 'TPE',
                'statut' => 'prospect',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.raison_sociale', 'Sec SARL');
    }

    public function test_secretaire_peut_modifier_entreprise(): void
    {
        $secretaire = User::factory()->create();
        $secretaire->assignRole('secretaire');
        $entreprise = Entreprise::factory()->create(['raison_sociale' => 'Avant']);

        $response = $this->actingAs($secretaire)
            ->putJson("/api/v1/entreprises/{$entreprise->id}", [
                'raison_sociale' => 'Apres Sec',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.raison_sociale', 'Apres Sec');
    }

    public function test_secretaire_ne_peut_pas_supprimer_entreprise(): void
    {
        $secretaire = User::factory()->create();
        $secretaire->assignRole('secretaire');
        $entreprise = Entreprise::factory()->create();

        $this->actingAs($secretaire)
            ->deleteJson("/api/v1/entreprises/{$entreprise->id}")
            ->assertForbidden();
    }
}
