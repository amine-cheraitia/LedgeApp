<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\Setting;
use App\Models\TvaTaux;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FactureApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Mission $mission;

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
                'actif' => true,
            ]
        );

        $exercice = Exercice::create([
            'annee' => (int) date('Y'),
            'date_ouverture' => date('Y').'-01-01',
            'date_cloture' => date('Y').'-12-31',
            'statut' => 'ouvert',
        ]);

        TvaTaux::create([
            'taux' => 19,
            'designation' => 'TVA standard',
            'date_debut' => '2023-01-01',
            'type' => 'standard',
            'actif' => true,
        ]);

        Setting::set('facture_prefixe', 'FF');
        Setting::set('avoir_prefixe', 'FA');

        $this->mission = Mission::create([
            'entreprise_id' => $entreprise->id,
            'prestation_id' => $prestation->id,
            'exercice_id' => $exercice->id,
            'reference' => 'M'.date('Y').'-001',
            'prix_ht' => 315000,
            'date_debut' => date('Y').'-01-01',
            'date_fin' => date('Y').'-12-31',
            'statut' => 'en_cours',
        ]);
    }

    public function test_can_create_facture_tranche1(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', [
                'mission_id' => $this->mission->id,
                'date_facture' => '2026-04-02',
            ]);

        $response->assertCreated();
        $data = $response->json('data');

        $this->assertEquals('FF', $data['type']);
        $this->assertEquals('en_attente', $data['statut_paiement']);
        // T1 = 30% de 315 000 = 94 500
        $this->assertEquals(94500, (float) $data['montant_ht']);
        $this->assertStringStartsWith('FF', $data['numero']);
        // Echeance = date_facture + 45 jours
        $this->assertEquals('2026-05-17', $data['date_echeance']);
    }

    public function test_facture_standard_sans_taux_en_vigueur_refuse(): void
    {
        // Le seul taux standard demarre le 2023-01-01 : facturer avant doit echouer (409),
        // au lieu d'appliquer silencieusement 0% sur une facture soumise a la TVA.
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', [
                'mission_id' => $this->mission->id,
                'date_facture' => '2022-12-31',
            ]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('factures', 0);
    }

    public function test_tranches_automatiques_t1_t2_t3(): void
    {
        // T1
        $r1 = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', ['mission_id' => $this->mission->id, 'date_facture' => '2026-04-02']);
        $r1->assertCreated();
        $this->assertEquals(94500, (float) $r1->json('data.montant_ht'));

        // T2
        $r2 = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', ['mission_id' => $this->mission->id, 'date_facture' => '2026-05-01']);
        $r2->assertCreated();
        $this->assertEquals(94500, (float) $r2->json('data.montant_ht'));

        // T3
        $r3 = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', ['mission_id' => $this->mission->id, 'date_facture' => '2026-06-01']);
        $r3->assertCreated();
        $this->assertEquals(126000, (float) $r3->json('data.montant_ht'));
    }

    public function test_erreur_si_plus_de_3_tranches(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->admin)
                ->postJson('/api/v1/factures', ['mission_id' => $this->mission->id, 'date_facture' => '2026-04-0'.($i + 1)]);
        }

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', ['mission_id' => $this->mission->id, 'date_facture' => '2026-07-01']);

        $response->assertStatus(409);
        $this->assertStringContainsString('3 tranches', $response->json('message'));
    }

    public function test_facture_snapshots_tva_immutably(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', [
                'mission_id' => $this->mission->id,
                'date_facture' => '2026-04-02',
            ]);

        $response->assertCreated();
        $data = $response->json('data');
        $this->assertEquals(19, (float) $data['taux_tva']);
        // TVA = 94500 * 19% = 17955
        $this->assertEquals(17955, (float) $data['montant_tva']);
        // TTC = 94500 + 17955 = 112455
        $this->assertEquals(112455, (float) $data['montant_ttc']);
    }

    public function test_can_add_paiement_to_facture(): void
    {
        $factureResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', [
                'mission_id' => $this->mission->id,
                'date_facture' => '2026-04-02',
            ]);

        $factureId = $factureResponse->json('data.id');

        $paiementResponse = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$factureId}/paiements", [
                'montant' => 50000,
                'date_paiement' => '2026-04-10',
                'mode_paiement' => 'virement',
            ]);

        $paiementResponse->assertCreated();

        $factureCheck = $this->actingAs($this->admin)
            ->getJson("/api/v1/factures/{$factureId}");

        $this->assertEquals('partiel', $factureCheck->json('data.statut_paiement'));
        $this->assertEquals(50000, (float) $factureCheck->json('data.montant_paye'));
        $this->assertEquals('virement', $factureCheck->json('data.mode_paiement'));
    }

    public function test_facture_becomes_solde_when_fully_paid(): void
    {
        $factureResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', [
                'mission_id' => $this->mission->id,
                'date_facture' => '2026-04-02',
            ]);

        $factureId = $factureResponse->json('data.id');
        $montantTtc = (float) $factureResponse->json('data.montant_ttc');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$factureId}/paiements", [
                'montant' => $montantTtc,
                'date_paiement' => '2026-04-10',
                'mode_paiement' => 'virement',
            ]);

        $factureCheck = $this->actingAs($this->admin)
            ->getJson("/api/v1/factures/{$factureId}");

        $this->assertEquals('solde', $factureCheck->json('data.statut_paiement'));
    }

    public function test_cannot_delete_facture_with_paiements(): void
    {
        $factureResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', [
                'mission_id' => $this->mission->id,
                'date_facture' => '2026-04-02',
            ]);

        $factureId = $factureResponse->json('data.id');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$factureId}/paiements", [
                'montant' => 1000,
                'date_paiement' => '2026-04-10',
                'mode_paiement' => 'cheque',
            ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/factures/{$factureId}");

        $response->assertStatus(409);
    }

    public function test_cannot_pay_solde_facture(): void
    {
        $factureResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', [
                'mission_id' => $this->mission->id,
                'date_facture' => '2026-04-02',
            ]);

        $factureId = $factureResponse->json('data.id');
        $montantTtc = (float) $factureResponse->json('data.montant_ttc');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$factureId}/paiements", [
                'montant' => $montantTtc,
                'date_paiement' => '2026-04-10',
                'mode_paiement' => 'virement',
            ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$factureId}/paiements", [
                'montant' => 1000,
                'date_paiement' => '2026-04-11',
                'mode_paiement' => 'virement',
            ]);

        $response->assertStatus(409);
    }
}
