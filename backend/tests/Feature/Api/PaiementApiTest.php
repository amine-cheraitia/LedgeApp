<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Events\InvoicePaid;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\Mission;
use App\Models\Paiement;
use App\Models\Prestation;
use App\Models\Setting;
use App\Models\TvaTaux;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaiementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $secretaire;

    private User $collaborateur;

    private Facture $facture;

    private float $montantTtc;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'secretaire']);
        Role::firstOrCreate(['name' => 'collaborateur']);
        Role::firstOrCreate(['name' => 'client']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->secretaire = User::factory()->create();
        $this->secretaire->assignRole('secretaire');

        $this->collaborateur = User::factory()->create();
        $this->collaborateur->assignRole('collaborateur');

        // Contexte métier minimal pour créer une facture via l'API
        $entreprise = Entreprise::factory()->create([
            'regime_fiscal' => 'reel',
            'categorie' => 'PME',
        ]);

        $prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            ['designation' => 'Accompagnement comptable', 'tarif_initial' => 120000, 'duree_mois' => 12, 'actif' => true],
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

        $mission = Mission::create([
            'entreprise_id' => $entreprise->id,
            'prestation_id' => $prestation->id,
            'exercice_id' => $exercice->id,
            'reference' => 'M'.date('Y').'-001',
            'prix_ht' => 315000,
            'date_debut' => date('Y').'-01-01',
            'date_fin' => date('Y').'-12-31',
            'statut' => 'en_cours',
        ]);

        $factureResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/factures', [
                'mission_id' => $mission->id,
                'date_facture' => date('Y').'-04-01',
            ]);

        $this->facture = Facture::findOrFail($factureResponse->json('data.id'));
        $this->montantTtc = (float) $this->facture->montant_ttc;
    }

    // ─── Listing ─────────────────────────────────────────────────────────────

    public function test_index_retourne_les_paiements_avec_recorded_by_name(): void
    {
        Paiement::create([
            'facture_id' => $this->facture->id,
            'recorded_by' => $this->admin->id,
            'montant' => 10000,
            'date_paiement' => date('Y').'-04-10',
            'mode_paiement' => 'virement',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/factures/{$this->facture->id}/paiements");

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertArrayHasKey('recorded_by_name', $response->json('data.0'));
        $this->assertEquals($this->admin->name, $response->json('data.0.recorded_by_name'));
    }

    // ─── Création — droits ────────────────────────────────────────────────────

    public function test_admin_peut_enregistrer_un_paiement(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/paiements", [
                'montant' => 10000,
                'date_paiement' => date('Y').'-04-10',
                'mode_paiement' => 'virement',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('paiements', [
            'facture_id' => $this->facture->id,
            'recorded_by' => $this->admin->id,
            'montant' => 10000,
        ]);
    }

    public function test_secretaire_peut_enregistrer_un_paiement(): void
    {
        $response = $this->actingAs($this->secretaire)
            ->postJson("/api/v1/factures/{$this->facture->id}/paiements", [
                'montant' => 10000,
                'date_paiement' => date('Y').'-04-10',
                'mode_paiement' => 'cheque',
                'reference' => 'CHQ-001',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('paiements', [
            'facture_id' => $this->facture->id,
            'recorded_by' => $this->secretaire->id,
        ]);
    }

    public function test_collaborateur_ne_peut_pas_enregistrer_un_paiement(): void
    {
        $response = $this->actingAs($this->collaborateur)
            ->postJson("/api/v1/factures/{$this->facture->id}/paiements", [
                'montant' => 10000,
                'date_paiement' => date('Y').'-04-10',
                'mode_paiement' => 'virement',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('paiements', 0);
    }

    // ─── Création — validation des montants ──────────────────────────────────

    public function test_montant_zero_est_rejete(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/paiements", [
                'montant' => 0,
                'date_paiement' => date('Y').'-04-10',
                'mode_paiement' => 'virement',
            ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('paiements', 0);
    }

    public function test_montant_negatif_est_rejete(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/paiements", [
                'montant' => -5000,
                'date_paiement' => date('Y').'-04-10',
                'mode_paiement' => 'virement',
            ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('paiements', 0);
    }

    public function test_montant_superieur_au_restant_du_est_rejete(): void
    {
        $montantExcessif = $this->montantTtc + 1;

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/paiements", [
                'montant' => $montantExcessif,
                'date_paiement' => date('Y').'-04-10',
                'mode_paiement' => 'virement',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.montant.0', fn ($v) => str_contains($v, 'dépasser'));
        $this->assertDatabaseCount('paiements', 0);
    }

    public function test_facture_deja_soldee_est_rejetee(): void
    {
        // Solder la facture
        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/paiements", [
                'montant' => $this->montantTtc,
                'date_paiement' => date('Y').'-04-10',
                'mode_paiement' => 'virement',
            ]);

        // Tenter un deuxième paiement
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/paiements", [
                'montant' => 1000,
                'date_paiement' => date('Y').'-04-11',
                'mode_paiement' => 'virement',
            ]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('paiements', 1);
    }

    public function test_invoice_paid_event_dispatche_quand_facture_soldee(): void
    {
        Event::fake([InvoicePaid::class]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/paiements", [
                'montant' => $this->montantTtc,
                'date_paiement' => date('Y').'-04-10',
                'mode_paiement' => 'virement',
            ]);

        Event::assertDispatched(InvoicePaid::class);
    }

    public function test_paiement_partiel_ne_declenche_pas_invoice_paid(): void
    {
        Event::fake([InvoicePaid::class]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/paiements", [
                'montant' => 1000,
                'date_paiement' => date('Y').'-04-10',
                'mode_paiement' => 'virement',
            ]);

        Event::assertNotDispatched(InvoicePaid::class);
    }

    // ─── Suppression — droits ────────────────────────────────────────────────

    public function test_admin_peut_supprimer_nimporte_quel_paiement(): void
    {
        $paiement = Paiement::create([
            'facture_id' => $this->facture->id,
            'recorded_by' => $this->secretaire->id,
            'montant' => 5000,
            'date_paiement' => date('Y').'-04-10',
            'mode_paiement' => 'virement',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/factures/{$this->facture->id}/paiements/{$paiement->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('paiements', ['id' => $paiement->id]);
    }

    public function test_secretaire_peut_supprimer_sa_propre_saisie(): void
    {
        $paiement = Paiement::create([
            'facture_id' => $this->facture->id,
            'recorded_by' => $this->secretaire->id,
            'montant' => 5000,
            'date_paiement' => date('Y').'-04-10',
            'mode_paiement' => 'cheque',
        ]);

        $response = $this->actingAs($this->secretaire)
            ->deleteJson("/api/v1/factures/{$this->facture->id}/paiements/{$paiement->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('paiements', ['id' => $paiement->id]);
    }

    public function test_secretaire_ne_peut_pas_supprimer_la_saisie_dautrui(): void
    {
        $autreSecretaire = User::factory()->create();
        $autreSecretaire->assignRole('secretaire');

        $paiement = Paiement::create([
            'facture_id' => $this->facture->id,
            'recorded_by' => $autreSecretaire->id,
            'montant' => 5000,
            'date_paiement' => date('Y').'-04-10',
            'mode_paiement' => 'virement',
        ]);

        $response = $this->actingAs($this->secretaire)
            ->deleteJson("/api/v1/factures/{$this->facture->id}/paiements/{$paiement->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('paiements', ['id' => $paiement->id]);
    }

    public function test_suppression_recalcule_le_statut_de_la_facture(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/paiements", [
                'montant' => 5000,
                'date_paiement' => date('Y').'-04-10',
                'mode_paiement' => 'virement',
            ]);

        $paiementId = $response->json('data.id');

        // Après création via API le statut est partiel
        $this->facture->refresh();
        $this->assertEquals('partiel', $this->facture->statut_paiement);

        // Après suppression, retour en attente
        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/factures/{$this->facture->id}/paiements/{$paiementId}");

        $this->facture->refresh();
        $this->assertEquals('en_attente', $this->facture->statut_paiement);
        $this->assertEquals(0, (float) $this->facture->montant_paye);
    }

    public function test_supprimer_un_paiement_via_une_autre_facture_renvoie_404(): void
    {
        // Le paiement appartient a la facture A ; le supprimer via l'URL d'une
        // facture B doit renvoyer 404 (sinon on recalculerait le statut de B a tort).
        $paiement = Paiement::create([
            'facture_id' => $this->facture->id,
            'recorded_by' => $this->admin->id,
            'montant' => 5000,
            'date_paiement' => date('Y').'-04-10',
            'mode_paiement' => 'virement',
        ]);

        $missionA = $this->facture->mission;
        $missionB = Mission::create([
            'entreprise_id' => $missionA->entreprise_id,
            'prestation_id' => $missionA->prestation_id,
            'exercice_id' => $missionA->exercice_id,
            'reference' => 'M'.date('Y').'-002',
            'prix_ht' => 315000,
            'date_debut' => date('Y').'-01-01',
            'date_fin' => date('Y').'-12-31',
            'statut' => 'en_cours',
        ]);
        $factureB = Facture::findOrFail(
            $this->actingAs($this->admin)
                ->postJson('/api/v1/factures', ['mission_id' => $missionB->id, 'date_facture' => date('Y').'-04-01'])
                ->json('data.id')
        );

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/factures/{$factureB->id}/paiements/{$paiement->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('paiements', ['id' => $paiement->id]);
    }
}
