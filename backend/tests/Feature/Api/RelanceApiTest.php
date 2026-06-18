<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\RelanceClientMail;
use App\Models\Contact;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\Relance;
use App\Models\Setting;
use App\Models\TvaTaux;
use App\Models\User;
use App\Services\RelanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RelanceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Facture $factureEnAttente;

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
            'email' => 'client@example.com',
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

        $tvaTaux = TvaTaux::create([
            'taux' => 19,
            'designation' => 'TVA standard',
            'date_debut' => '2023-01-01',
            'type' => 'standard',
            'actif' => true,
        ]);

        Setting::set('relance_prefixe', 'R');

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

        $this->factureEnAttente = Facture::create([
            'entreprise_id' => $entreprise->id,
            'exercice_id' => $exercice->id,
            'mission_id' => $mission->id,
            'created_by' => $this->admin->id,
            'tva_taux_id' => $tvaTaux->id,
            'numero' => 'FF'.date('Y').'-001',
            'type' => 'FF',
            'date_facture' => date('Y').'-01-15',
            'date_echeance' => date('Y').'-03-01',
            'montant_ht' => 94500,
            'taux_tva' => 19,
            'montant_tva' => 17955,
            'montant_ttc' => 112455,
            'montant_paye' => 0,
            'statut_paiement' => 'en_attente',
            'mode_paiement' => 'non_defini',
        ]);
    }

    // -------------------------------------------------------------------------
    // US-27 — Creances
    // -------------------------------------------------------------------------

    public function test_can_list_creances(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/creances');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->factureEnAttente->numero, $data[0]['numero']);
        $this->assertArrayHasKey('montant_restant', $data[0]);
    }

    public function test_creances_excludes_factures_soldees(): void
    {
        $this->factureEnAttente->update(['statut_paiement' => 'solde']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/creances');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_creances_includes_factures_partielles(): void
    {
        $this->factureEnAttente->update([
            'statut_paiement' => 'partiel',
            'montant_paye' => 50000,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/creances');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // US-28 — Relance manuelle
    // -------------------------------------------------------------------------

    public function test_can_send_relance_manuelle(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->factureEnAttente->id}/relances", [
                'niveau' => 1,
            ]);

        $response->assertCreated();
        $data = $response->json();
        $relance = $data['data'] ?? $data;
        $this->assertEquals(1, $relance['niveau']);
        $this->assertEquals('manuelle', $relance['type']);
        $this->assertEquals('envoyee', $relance['statut']);
        $this->assertEquals('client@example.com', $relance['email_destinataire']);

        $this->assertDatabaseHas('relances', [
            'facture_id' => $this->factureEnAttente->id,
            'niveau' => 1,
            'type' => 'manuelle',
            'statut' => 'envoyee',
        ]);
    }

    public function test_relance_manuelle_envoyee_au_contact_principal(): void
    {
        Mail::fake();

        Contact::create([
            'entreprise_id' => $this->factureEnAttente->entreprise->id,
            'nom' => 'Diallo',
            'email' => 'contact.principal@example.com',
            'est_principal' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->factureEnAttente->id}/relances", ['niveau' => 1]);

        $response->assertCreated();
        // Le contact principal prime sur l'email general de l'entreprise
        $this->assertEquals('contact.principal@example.com', $response->json('data.email_destinataire'));

        Mail::assertSent(RelanceClientMail::class, fn (RelanceClientMail $mail) => $mail->hasTo('contact.principal@example.com'));
    }

    public function test_relance_manuelle_refusee_si_aucune_adresse(): void
    {
        Mail::fake();

        // Ni contact principal, ni email entreprise
        $this->factureEnAttente->entreprise->update(['email' => null]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->factureEnAttente->id}/relances", ['niveau' => 1])
            ->assertStatus(422);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('relances', 0);
    }

    public function test_relance_echec_envoi_renvoie_422_et_ne_persiste_pas(): void
    {
        // Simule une panne d'envoi (SMTP/config) : la reponse doit etre propre (422), pas un 500,
        // et la relance ne doit pas etre persistee (rollback de la transaction).
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->factureEnAttente->id}/relances", ['niveau' => 1])
            ->assertStatus(422);

        $this->assertDatabaseCount('relances', 0);
    }

    public function test_cannot_send_relance_on_facture_soldee(): void
    {
        $this->factureEnAttente->update(['statut_paiement' => 'solde']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->factureEnAttente->id}/relances", [
                'niveau' => 1,
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_send_relance_without_niveau(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->factureEnAttente->id}/relances", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['niveau']);
    }

    public function test_can_list_relances_for_facture(): void
    {
        Relance::create([
            'facture_id' => $this->factureEnAttente->id,
            'sent_by' => $this->admin->id,
            'niveau' => 1,
            'type' => 'manuelle',
            'email_destinataire' => 'client@example.com',
            'envoyee_le' => now(),
            'statut' => 'envoyee',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/factures/{$this->factureEnAttente->id}/relances");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // US-26 — Relance automatique (RelanceService)
    // -------------------------------------------------------------------------

    public function test_relance_automatique_ne_duplique_pas(): void
    {
        Mail::fake();

        Relance::create([
            'facture_id' => $this->factureEnAttente->id,
            'niveau' => 1,
            'type' => 'automatique',
            'email_destinataire' => 'client@example.com',
            'envoyee_le' => now(),
            'statut' => 'envoyee',
        ]);

        $service = app(RelanceService::class);
        $result = $service->envoyerAutomatique($this->factureEnAttente->load('entreprise', 'relances'), 1);

        $this->assertNull($result);
        $this->assertCount(1, Relance::where('facture_id', $this->factureEnAttente->id)->get());
    }

    public function test_relance_niveau2_bloquee_sans_niveau1(): void
    {
        Mail::fake();

        $service = app(RelanceService::class);
        $result = $service->envoyerAutomatique($this->factureEnAttente->load('entreprise', 'relances'), 2);

        $this->assertNull($result);
        $this->assertCount(0, Relance::where('facture_id', $this->factureEnAttente->id)->get());
    }
}
