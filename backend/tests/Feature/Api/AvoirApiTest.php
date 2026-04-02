<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Avoir;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\TvaTaux;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AvoirApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Facture $facture;

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
            'regime_fiscal' => 'reel',
            'categorie' => 'PME',
        ]);

        $prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            [
                'designation' => 'Accompagnement comptable',
                'tarif_initial' => 120000,
                'duree_mois' => 12,
            ]
        );

        $exercice = Exercice::factory()->create(['annee' => 2026, 'statut' => 'ouvert']);

        $tvaTaux = TvaTaux::create([
            'designation' => 'TVA 19%',
            'taux' => 19,
            'date_debut' => '2020-01-01',
            'date_fin' => null,
        ]);

        $mission = Mission::factory()->create([
            'entreprise_id' => $entreprise->id,
            'exercice_id' => $exercice->id,
            'prestation_id' => $prestation->id,
            'prix_ht' => 315000,
        ]);

        $this->facture = Facture::create([
            'entreprise_id' => $entreprise->id,
            'exercice_id' => $exercice->id,
            'mission_id' => $mission->id,
            'created_by' => $this->admin->id,
            'tva_taux_id' => $tvaTaux->id,
            'numero' => 'FF2026-001',
            'type' => 'FF',
            'date_facture' => '2026-01-15',
            'date_echeance' => '2026-03-01',
            'montant_ht' => 94500,
            'taux_tva' => 19,
            'montant_tva' => 17955,
            'montant_timbre' => 0,
            'montant_ttc' => 112455,
            'montant_paye' => 0,
            'statut_paiement' => 'en_attente',
            'mode_paiement' => 'non_defini',
        ]);
    }

    public function test_index_retourne_la_liste_des_avoirs(): void
    {
        Avoir::create([
            'facture_origine_id' => $this->facture->id,
            'exercice_id' => $this->facture->exercice_id,
            'created_by' => $this->admin->id,
            'numero' => 'FA2026-001',
            'date_avoir' => '2026-02-01',
            'montant_ht' => 10000,
            'taux_tva_snapshot' => 19,
            'montant_tva' => 1900,
            'montant_ttc' => 11900,
            'motif' => 'Correction erreur de facturation',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/factures/{$this->facture->id}/avoirs");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_creation_avoir_valide(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/avoirs", [
                'montant_ht' => 10000,
                'date_avoir' => '2026-02-01',
                'motif' => 'Correction erreur de facturation',
            ]);

        $response->assertCreated();

        $data = $response->json('data') ?? $response->json();
        $this->assertEquals('FA2026-001', $data['numero']);
        $this->assertEquals(19, $data['taux_tva_snapshot']);
        $this->assertEquals(11900, $data['montant_ttc']);
        $this->assertDatabaseHas('avoirs', ['numero' => 'FA2026-001']);
    }

    public function test_tva_reprise_de_la_facture_origine(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/avoirs", [
                'montant_ht' => 5000,
                'date_avoir' => '2026-02-01',
                'motif' => 'Test TVA snapshot',
            ]);

        $response->assertCreated();
        $data = $response->json('data') ?? $response->json();
        $this->assertEquals(19.0, (float) $data['taux_tva_snapshot']);
        $this->assertEquals(950.0, round((float) $data['montant_tva'], 2));
    }

    public function test_numerotation_sequentielle(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/avoirs", [
                'montant_ht' => 1000,
                'date_avoir' => '2026-02-01',
                'motif' => 'Premier avoir',
            ])->assertCreated();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/avoirs", [
                'montant_ht' => 500,
                'date_avoir' => '2026-02-02',
                'motif' => 'Deuxième avoir',
            ]);

        $response->assertCreated();
        $data = $response->json('data') ?? $response->json();
        $this->assertEquals('FA2026-002', $data['numero']);
    }

    public function test_montant_superieur_au_restant_du_retourne_409(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/avoirs", [
                'montant_ht' => 200000,
                'date_avoir' => '2026-02-01',
                'motif' => 'Montant trop élevé',
            ]);

        $response->assertStatus(409);
        $this->assertStringContainsString('restant', $response->json('message'));
    }

    public function test_avoir_reduit_le_montant_restant(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/avoirs", [
                'montant_ht' => 10000,
                'date_avoir' => '2026-02-01',
                'motif' => 'Avoir partiel',
            ])->assertCreated();

        $this->facture->refresh();
        // montant_ttc = 112455, montant_paye = 0, avoir_ttc = 11900
        // restant = 112455 - 0 - 11900 = 100555
        $this->assertEquals(100555.0, round($this->facture->montantRestant(), 2));
    }

    public function test_validation_montant_requis(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/avoirs", [
                'date_avoir' => '2026-02-01',
                'motif' => 'Sans montant',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['montant_ht']);
    }

    public function test_validation_motif_requis(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/avoirs", [
                'montant_ht' => 1000,
                'date_avoir' => '2026-02-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motif']);
    }

    public function test_acces_refuse_sans_authentification(): void
    {
        $this->postJson("/api/v1/factures/{$this->facture->id}/avoirs", [
            'montant_ht' => 1000,
            'date_avoir' => '2026-02-01',
            'motif' => 'Test',
        ])->assertStatus(401);
    }
}
