<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CategorieEntreprise;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Prestation;
use App\Models\RegimeFiscal;
use App\Models\Setting;
use App\Models\TimbreTaux;
use App\Models\TvaTaux;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MissionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Entreprise $entreprise;

    private Prestation $prestation;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->entreprise = Entreprise::factory()->create([
            'regime_fiscal' => 'reel',
            'categorie' => 'PME',
            'statut' => 'prospect',
        ]);

        $this->prestation = Prestation::create([
            'code' => 'ACMPT',
            'designation' => 'Assistance Comptable',
            'tarif_initial' => 120000,
            'duree_mois' => 12,
            'actif' => true,
        ]);

        RegimeFiscal::create(['code' => 'reel', 'designation' => 'Reel', 'indice' => 1.5]);
        RegimeFiscal::create(['code' => 'forfait', 'designation' => 'Forfait', 'indice' => 1.0]);
        CategorieEntreprise::create(['code' => 'PME', 'designation' => 'PME', 'indice' => 1.75]);
        CategorieEntreprise::create(['code' => 'TPE', 'designation' => 'TPE', 'indice' => 1.0]);

        Exercice::create([
            'annee' => (int) date('Y'),
            'date_ouverture' => date('Y').'-01-01',
            'date_cloture' => date('Y').'-12-31',
            'statut' => 'ouvert',
        ]);
    }

    public function test_can_create_mission_with_calculated_prix_ht(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_debut' => '2026-04-01',
                'date_fin' => '2027-03-31',
            ]);

        $response->assertCreated();
        $data = $response->json('data');

        // Prix HT = 120000 × 1.5 (reel) × 1.75 (PME) = 315000
        $this->assertEquals(315000, (float) $data['prix_ht']);
        $this->assertStringStartsWith('M', $data['reference']);
        $this->assertEquals('en_cours', $data['statut']);
    }

    public function test_mission_creation_converts_prospect_to_client(): void
    {
        $this->assertEquals('prospect', $this->entreprise->statut);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_debut' => '2026-04-01',
                'date_fin' => '2027-03-31',
            ]);

        $this->entreprise->refresh();
        $this->assertEquals('client', $this->entreprise->statut);
    }

    public function test_can_list_missions(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_debut' => '2026-04-01',
                'date_fin' => '2027-03-31',
            ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/missions');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_show_mission_detail(): void
    {
        $createResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_debut' => '2026-04-01',
                'date_fin' => '2027-03-31',
            ]);

        $missionId = $createResponse->json('data.id');

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/missions/{$missionId}");

        $response->assertOk();
        $this->assertArrayHasKey('entreprise', $response->json('data'));
        $this->assertArrayHasKey('prestation', $response->json('data'));
    }

    public function test_can_update_mission_statut(): void
    {
        $createResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_debut' => '2026-04-01',
                'date_fin' => '2027-03-31',
            ]);

        $missionId = $createResponse->json('data.id');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/missions/{$missionId}", [
                'statut' => 'terminee',
            ]);

        $response->assertOk();
        $this->assertEquals('terminee', $response->json('data.statut'));
    }

    public function test_cannot_delete_mission_with_factures(): void
    {
        $createResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_debut' => '2026-04-01',
                'date_fin' => '2027-03-31',
            ]);

        $missionId = $createResponse->json('data.id');

        // Creer les rates pour la facture
        TvaTaux::create([
            'taux' => 19,
            'designation' => 'TVA standard',
            'date_debut' => '2023-01-01',
            'type' => 'standard',
            'actif' => true,
        ]);
        TimbreTaux::create([
            'taux' => 1,
            'plafond' => 2500,
            'designation' => 'Timbre fiscal',
            'date_debut' => '2024-01-01',
            'actif' => true,
        ]);
        Setting::set('facture_prefixe', 'FF');

        // Creer une facture liee
        $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', [
                'entreprise_id' => $this->entreprise->id,
                'mission_id' => $missionId,
                'type' => 'FF',
                'date_facture' => '2026-04-01',
                'date_echeance' => '2026-05-01',
                'lignes' => [
                    ['designation' => 'Service', 'quantite' => 1, 'prix_unitaire_ht' => 100000],
                ],
            ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/missions/{$missionId}");

        $response->assertStatus(409);
    }

    public function test_sequential_reference_numbering(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_debut' => '2026-04-01',
                'date_fin' => '2027-03-31',
            ]);

        $response2 = $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_debut' => '2026-05-01',
                'date_fin' => '2027-04-30',
            ]);

        $ref = $response2->json('data.reference');
        $this->assertStringEndsWith('-002', $ref);
    }

    public function test_convention_pdf_generates_and_stores_numero(): void
    {
        Setting::set('cabinet_nom', 'Cabinet Test');

        $mission = $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_debut' => '2026-04-01',
                'date_fin' => '2027-03-31',
            ])
            ->json('data');

        // Premier appel : génère le numéro
        $response = $this->actingAs($this->admin)
            ->get("/api/v1/missions/{$mission['id']}/convention/pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        // Le numéro a été stocké
        $this->assertDatabaseHas('missions', [
            'id' => $mission['id'],
        ]);
        $missionUpdated = \App\Models\Mission::find($mission['id']);
        $this->assertNotNull($missionUpdated->convention_numero);
        $this->assertStringStartsWith('CV', $missionUpdated->convention_numero);

        // Deuxième appel : réutilise le même numéro
        $this->actingAs($this->admin)
            ->get("/api/v1/missions/{$mission['id']}/convention/pdf")
            ->assertOk();

        $this->assertSame($missionUpdated->convention_numero, $missionUpdated->fresh()->convention_numero);
    }

    public function test_mandat_pdf_generates_and_stores_numero(): void
    {
        Setting::set('cabinet_nom', 'Cabinet Test');

        $mission = $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_debut' => '2026-04-01',
                'date_fin' => '2027-03-31',
            ])
            ->json('data');

        $response = $this->actingAs($this->admin)
            ->get("/api/v1/missions/{$mission['id']}/mandat/pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $missionUpdated = \App\Models\Mission::find($mission['id']);
        $this->assertNotNull($missionUpdated->mandat_numero);
        $this->assertStringStartsWith('MD', $missionUpdated->mandat_numero);
    }
}
