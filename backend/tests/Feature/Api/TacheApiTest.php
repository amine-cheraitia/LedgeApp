<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CategorieEntreprise;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Prestation;
use App\Models\RegimeFiscal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TacheApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $missionId;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $entreprise = Entreprise::factory()->create([
            'regime_fiscal' => 'forfait',
            'categorie' => 'TPE',
            'statut' => 'prospect',
        ]);

        $prestation = Prestation::create([
            'code' => 'ACMPT',
            'designation' => 'Assistance Comptable',
            'tarif_initial' => 120000,
            'duree_mois' => 12,
            'actif' => true,
        ]);

        RegimeFiscal::create(['code' => 'forfait', 'designation' => 'Forfait', 'indice' => 1.0]);
        CategorieEntreprise::create(['code' => 'TPE', 'designation' => 'TPE', 'indice' => 1.0]);

        Exercice::create([
            'annee' => (int) date('Y'),
            'date_ouverture' => date('Y').'-01-01',
            'date_cloture' => date('Y').'-12-31',
            'statut' => 'ouvert',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $entreprise->id,
                'prestation_id' => $prestation->id,
                'date_debut' => '2026-04-01',
                'date_fin' => '2027-03-31',
            ]);

        $this->missionId = $response->json('data.id');
    }

    public function test_can_create_tache(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Collecte documents',
                'description' => 'Recuperer les documents comptables',
                'priorite' => 2,
            ]);

        $response->assertCreated();
        $this->assertEquals('Collecte documents', $response->json('data.titre'));
        $this->assertEquals('a_faire', $response->json('data.statut'));
    }

    public function test_can_list_taches(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Tache 1',
            ]);
        $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Tache 2',
            ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/missions/{$this->missionId}/taches");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_update_tache_statut(): void
    {
        $createResponse = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Ma tache',
            ]);

        $tacheId = $createResponse->json('data.id');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/missions/{$this->missionId}/taches/{$tacheId}", [
                'titre' => 'Ma tache',
                'statut' => 'terminee',
            ]);

        $response->assertOk();
        $this->assertEquals('terminee', $response->json('data.statut'));
    }
}
