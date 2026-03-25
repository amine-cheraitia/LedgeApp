<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Setting;
use App\Models\TimbreRate;
use App\Models\TvaRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DevisApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Entreprise $entreprise;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->entreprise = Entreprise::factory()->create();

        Exercice::create([
            'annee' => (int) date('Y'),
            'date_ouverture' => date('Y').'-01-01',
            'date_cloture' => date('Y').'-12-31',
            'statut' => 'ouvert',
        ]);

        TvaRate::create([
            'taux' => 19,
            'designation' => 'TVA standard',
            'date_debut' => '2023-01-01',
            'type' => 'standard',
            'actif' => true,
        ]);

        TimbreRate::create([
            'taux' => 1,
            'plafond' => 2500,
            'designation' => 'Timbre fiscal',
            'date_debut' => '2024-01-01',
            'actif' => true,
        ]);

        Setting::set('devis_prefixe', 'DV');
    }

    public function test_can_create_devis_with_lines(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/devis', [
                'entreprise_id' => $this->entreprise->id,
                'date_devis' => '2026-03-25',
                'date_validite' => '2026-04-25',
                'lignes' => [
                    [
                        'designation' => 'Prestation comptable',
                        'quantite' => 1,
                        'prix_unitaire_ht' => 120000,
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.statut', 'brouillon');

        $data = $response->json('data');
        $this->assertEquals(120000, (float) $data['montant_ht']);
        $this->assertEquals(22800, (float) $data['montant_tva']);
        $this->assertStringStartsWith('DV', $data['numero']);
    }

    public function test_devis_calculates_tva_and_timbre(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/devis', [
                'entreprise_id' => $this->entreprise->id,
                'date_devis' => '2026-03-25',
                'date_validite' => '2026-04-25',
                'lignes' => [
                    ['designation' => 'Ligne 1', 'quantite' => 2, 'prix_unitaire_ht' => 50000],
                ],
            ]);

        $response->assertCreated();
        $data = $response->json('data');

        // HT = 2 * 50000 = 100000
        $this->assertEquals(100000, (float) $data['montant_ht']);
        // TVA = 100000 * 19% = 19000
        $this->assertEquals(19000, (float) $data['montant_tva']);
        // Timbre = min(100000 * 1%, 2500) = 1000
        $this->assertEquals(1000, (float) $data['montant_timbre']);
        // TTC = 100000 + 19000 + 1000 = 120000
        $this->assertEquals(120000, (float) $data['montant_ttc']);
    }

    public function test_can_list_devis(): void
    {
        // Cree un devis via l'API d'abord
        $this->actingAs($this->admin)
            ->postJson('/api/v1/devis', [
                'entreprise_id' => $this->entreprise->id,
                'date_devis' => '2026-03-25',
                'date_validite' => '2026-04-25',
                'lignes' => [
                    ['designation' => 'Test', 'quantite' => 1, 'prix_unitaire_ht' => 10000],
                ],
            ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/devis');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_cannot_delete_non_brouillon_devis(): void
    {
        $createResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/devis', [
                'entreprise_id' => $this->entreprise->id,
                'date_devis' => '2026-03-25',
                'date_validite' => '2026-04-25',
                'lignes' => [
                    ['designation' => 'Test', 'quantite' => 1, 'prix_unitaire_ht' => 10000],
                ],
            ]);

        $devisId = $createResponse->json('data.id');

        // Passe en envoye
        $this->actingAs($this->admin)
            ->putJson("/api/v1/devis/{$devisId}", ['statut' => 'envoye']);

        // Tente de supprimer
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/devis/{$devisId}");

        $response->assertStatus(409);
    }

    public function test_sequential_numbering(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->admin)
                ->postJson('/api/v1/devis', [
                    'entreprise_id' => $this->entreprise->id,
                    'date_devis' => '2026-03-25',
                    'date_validite' => '2026-04-25',
                    'lignes' => [
                        ['designation' => "Ligne {$i}", 'quantite' => 1, 'prix_unitaire_ht' => 10000],
                    ],
                ]);
        }

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/devis');

        $numeros = collect($response->json('data'))->pluck('numero')->sort()->values();
        $annee = date('Y');
        $this->assertEquals("DV{$annee}-001", $numeros[0]);
        $this->assertEquals("DV{$annee}-002", $numeros[1]);
        $this->assertEquals("DV{$annee}-003", $numeros[2]);
    }
}
