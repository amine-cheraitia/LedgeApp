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

class FactureApiTest extends TestCase
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

        Setting::set('facture_prefixe', 'FF');
        Setting::set('avoir_prefixe', 'FA');
    }

    public function test_can_create_facture(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', [
                'entreprise_id' => $this->entreprise->id,
                'type' => 'FF',
                'date_facture' => '2026-03-25',
                'date_echeance' => '2026-04-25',
                'lignes' => [
                    ['designation' => 'Mission comptable', 'quantite' => 1, 'prix_unitaire_ht' => 315000],
                ],
            ]);

        $response->assertCreated();
        $data = $response->json('data');

        $this->assertEquals('FF', $data['type']);
        $this->assertEquals('en_attente', $data['statut_paiement']);
        $this->assertEquals(315000, (float) $data['montant_ht']);
        $this->assertStringStartsWith('FF', $data['numero']);
    }

    public function test_facture_snapshots_tva_immutably(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', [
                'entreprise_id' => $this->entreprise->id,
                'type' => 'FF',
                'date_facture' => '2026-03-25',
                'date_echeance' => '2026-04-25',
                'lignes' => [
                    ['designation' => 'Service', 'quantite' => 1, 'prix_unitaire_ht' => 100000],
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(19, (float) $data['taux_tva']);
        $this->assertEquals(19000, (float) $data['montant_tva']);
    }

    public function test_can_add_paiement_to_facture(): void
    {
        $factureResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', [
                'entreprise_id' => $this->entreprise->id,
                'type' => 'FF',
                'date_facture' => '2026-03-25',
                'date_echeance' => '2026-04-25',
                'lignes' => [
                    ['designation' => 'Service', 'quantite' => 1, 'prix_unitaire_ht' => 100000],
                ],
            ]);

        $factureId = $factureResponse->json('data.id');

        $paiementResponse = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$factureId}/paiements", [
                'montant' => 50000,
                'date_paiement' => '2026-03-26',
                'mode_paiement' => 'virement',
            ]);

        $paiementResponse->assertCreated();

        // Verifie que le statut est passe a partiel
        $factureCheck = $this->actingAs($this->admin)
            ->getJson("/api/v1/factures/{$factureId}");

        $this->assertEquals('partiel', $factureCheck->json('data.statut_paiement'));
        $this->assertEquals(50000, (float) $factureCheck->json('data.montant_paye'));
    }

    public function test_facture_becomes_solde_when_fully_paid(): void
    {
        $factureResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', [
                'entreprise_id' => $this->entreprise->id,
                'type' => 'FF',
                'date_facture' => '2026-03-25',
                'date_echeance' => '2026-04-25',
                'lignes' => [
                    ['designation' => 'Service', 'quantite' => 1, 'prix_unitaire_ht' => 100000],
                ],
            ]);

        $factureId = $factureResponse->json('data.id');
        $montantTtc = (float) $factureResponse->json('data.montant_ttc');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$factureId}/paiements", [
                'montant' => $montantTtc,
                'date_paiement' => '2026-03-26',
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
                'entreprise_id' => $this->entreprise->id,
                'type' => 'FF',
                'date_facture' => '2026-03-25',
                'date_echeance' => '2026-04-25',
                'lignes' => [
                    ['designation' => 'Service', 'quantite' => 1, 'prix_unitaire_ht' => 10000],
                ],
            ]);

        $factureId = $factureResponse->json('data.id');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$factureId}/paiements", [
                'montant' => 1000,
                'date_paiement' => '2026-03-26',
                'mode_paiement' => 'espece',
            ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/factures/{$factureId}");

        $response->assertStatus(409);
    }

    public function test_cannot_pay_solde_facture(): void
    {
        $factureResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', [
                'entreprise_id' => $this->entreprise->id,
                'type' => 'FF',
                'date_facture' => '2026-03-25',
                'date_echeance' => '2026-04-25',
                'lignes' => [
                    ['designation' => 'Service', 'quantite' => 1, 'prix_unitaire_ht' => 10000],
                ],
            ]);

        $factureId = $factureResponse->json('data.id');
        $montantTtc = (float) $factureResponse->json('data.montant_ttc');

        // Paye en totalite
        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$factureId}/paiements", [
                'montant' => $montantTtc,
                'date_paiement' => '2026-03-26',
                'mode_paiement' => 'virement',
            ]);

        // Tente de payer encore
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$factureId}/paiements", [
                'montant' => 1000,
                'date_paiement' => '2026-03-27',
                'mode_paiement' => 'virement',
            ]);

        $response->assertStatus(409);
    }
}
