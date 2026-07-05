<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\DevisMail;
use App\Mail\FactureMail;
use App\Mail\RelanceClientMail;
use App\Models\Avoir;
use App\Models\CategorieEntreprise;
use App\Models\Contact;
use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\RegimeFiscal;
use App\Models\Setting;
use App\Models\TvaTaux;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Couvre le cycle de vie complet de la facturation :
 * devis (creation/lignes/cycle/conversion/envoi), factures (show/transmission),
 * avoirs (creation/liste/suppression) et relances (envoi manuel).
 */
class FacturationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $collaborateur;

    private User $secretaire;

    private Entreprise $entreprise;

    private Prestation $prestation;

    private Exercice $exercice;

    private Mission $mission;

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

        $this->collaborateur = User::factory()->create();
        $this->collaborateur->assignRole('collaborateur');

        $this->secretaire = User::factory()->create();
        $this->secretaire->assignRole('secretaire');

        // Referentiel tarifaire : reel (1.5) x PME (1.75)
        RegimeFiscal::create(['code' => 'reel', 'designation' => 'Régime Réel', 'indice' => 1.50]);
        CategorieEntreprise::create(['code' => 'PME', 'designation' => 'PME', 'indice' => 1.75]);

        $this->entreprise = Entreprise::factory()->create([
            'regime_fiscal' => 'reel',
            'categorie' => 'PME',
            'email' => 'client@example.com',
        ]);

        // ACMPT : 120 000 x 1.5 x 1.75 = 315 000 DA
        $this->prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            [
                'designation' => 'Accompagnement comptable',
                'tarif_initial' => 120000,
                'duree_mois' => 12,
                'actif' => true,
            ]
        );

        $this->exercice = Exercice::create([
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

        Setting::set('devis_prefixe', 'DV');
        Setting::set('facture_prefixe', 'FF');
        Setting::set('avoir_prefixe', 'FA');
        Setting::set('mission_prefixe', 'M');
        Setting::set('relance_prefixe', 'R');

        // Creer la mission bascule l'entreprise prospect -> client (Observer). C'est attendu.
        $this->mission = Mission::create([
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'exercice_id' => $this->exercice->id,
            'reference' => 'M'.date('Y').'-001',
            'prix_ht' => 315000,
            'date_debut' => date('Y').'-01-01',
            'date_fin' => date('Y').'-12-31',
            'statut' => 'en_cours',
        ]);

        $this->facture = Facture::create([
            'entreprise_id' => $this->entreprise->id,
            'exercice_id' => $this->exercice->id,
            'mission_id' => $this->mission->id,
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

    private function creerDevisId(?int $entrepriseId = null): int
    {
        return (int) $this->actingAs($this->admin)->postJson('/api/v1/devis', [
            'entreprise_id' => $entrepriseId ?? $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'date_devis' => date('Y').'-06-01',
            'date_validite' => date('Y').'-07-01',
        ])->json('data.id');
    }

    private function creerFactureId(): int
    {
        return (int) $this->actingAs($this->admin)->postJson('/api/v1/factures', [
            'mission_id' => $this->mission->id,
            'date_facture' => date('Y').'-06-15',
        ])->json('data.id');
    }

    // -------------------------------------------------------------------------
    // Devis — creation, lignes, cycle de vie
    // -------------------------------------------------------------------------

    public function test_creer_devis_persiste_les_montants(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/devis', [
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'date_devis' => date('Y').'-06-01',
            'date_validite' => date('Y').'-07-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.statut', 'brouillon')
            ->assertJsonPath('data.prestation_id', $this->prestation->id);

        $data = $response->json('data');
        $this->assertEquals(315000, (float) $data['montant_ht']);
        $this->assertEquals(19, (float) $data['taux_tva']);
        $this->assertEquals(59850, (float) $data['montant_tva']);
        $this->assertEquals(374850, (float) $data['montant_ttc']);
        $this->assertStringStartsWith('DV', $data['numero']);
    }

    public function test_show_devis_charge_entreprise_et_prestation(): void
    {
        $devisId = $this->creerDevisId();

        $response = $this->actingAs($this->admin)->getJson("/api/v1/devis/{$devisId}");

        $response->assertOk()
            ->assertJsonPath('data.id', $devisId)
            ->assertJsonPath('data.entreprise.id', $this->entreprise->id)
            ->assertJsonPath('data.prestation.id', $this->prestation->id);
    }


    public function test_accepter_un_devis_envoye(): void
    {
        Mail::fake();
        $devisId = $this->creerDevisId();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/envoyer")
            ->assertOk()
            ->assertJsonPath('data.statut', 'envoye');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/accepter")
            ->assertOk()
            ->assertJsonPath('data.statut', 'accepte');

        $this->assertDatabaseHas('devis', ['id' => $devisId, 'statut' => 'accepte']);
    }

    public function test_refuser_un_devis_envoye(): void
    {
        Mail::fake();
        $devisId = $this->creerDevisId();

        $this->actingAs($this->admin)->postJson("/api/v1/devis/{$devisId}/envoyer");

        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/refuser")
            ->assertOk()
            ->assertJsonPath('data.statut', 'refuse');
    }

    public function test_accepter_un_devis_brouillon_echoue(): void
    {
        $devisId = $this->creerDevisId();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/accepter")
            ->assertStatus(409);
    }

    public function test_envoyer_devis_envoie_le_mail_au_contact_principal(): void
    {
        Mail::fake();

        Contact::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Diallo',
            'email' => 'contact.principal@example.com',
            'est_principal' => true,
        ]);

        $devisId = $this->creerDevisId();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/envoyer")
            ->assertOk()
            ->assertJsonPath('data.statut', 'envoye');

        Mail::assertSent(DevisMail::class, fn (DevisMail $mail) => $mail->hasTo('contact.principal@example.com'));
    }

    public function test_envoyer_devis_sans_adresse_retourne_409(): void
    {
        Mail::fake();
        $this->entreprise->update(['email' => null]);

        $devisId = $this->creerDevisId();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/envoyer")
            ->assertStatus(409);

        $this->assertEquals('brouillon', Devis::findOrFail($devisId)->statut);
        Mail::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Devis — conversion en mission
    // -------------------------------------------------------------------------

    public function test_convertir_devis_en_mission_cree_la_mission_et_bascule_prospect_client(): void
    {
        // Entreprise dediee (prospect) pour observer la bascule prospect -> client.
        $prospect = Entreprise::factory()->prospect()->create([
            'regime_fiscal' => 'reel',
            'categorie' => 'PME',
            'email' => 'prospect@example.com',
        ]);

        $devisId = $this->creerDevisId($prospect->id);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/convertir-en-mission", [
                'date_debut' => date('Y').'-06-01',
                'date_fin' => date('Y').'-09-30',
            ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.entreprise_id', $prospect->id)
            ->assertJsonPath('data.devis_id', $devisId);

        $missionId = $response->json('data.id');
        $this->assertDatabaseHas('missions', [
            'id' => $missionId,
            'devis_id' => $devisId,
            'entreprise_id' => $prospect->id,
        ]);

        // Bascule automatique via l'Observer / listener ConvertProspectToClient
        $this->assertEquals('client', $prospect->fresh()->statut);
    }

    public function test_convertir_devis_en_mission_attache_les_collaborateurs(): void
    {
        $devisId = $this->creerDevisId();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/convertir-en-mission", [
                'date_debut' => date('Y').'-06-01',
                'date_fin' => date('Y').'-09-30',
                'collaborateur_ids' => [$this->collaborateur->id],
            ]);

        $response->assertSuccessful();

        $mission = Mission::findOrFail($response->json('data.id'));
        $this->assertSame(1, $mission->collaborateurs()->count());
    }

    public function test_convertir_devis_sans_date_debut_est_rejete(): void
    {
        $devisId = $this->creerDevisId();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/convertir-en-mission", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_debut']);
    }

    // -------------------------------------------------------------------------
    // Factures — show (lignes) + transmission
    // -------------------------------------------------------------------------

    public function test_show_facture_expose_les_lignes(): void
    {
        $factureId = $this->creerFactureId();

        $response = $this->actingAs($this->admin)->getJson("/api/v1/factures/{$factureId}");

        $response->assertOk()
            ->assertJsonPath('data.id', $factureId)
            ->assertJsonCount(1, 'data.lignes')
            ->assertJsonPath('data.lignes.0.facture_id', $factureId);

        $this->assertNotEmpty($response->json('data.lignes.0.designation'));
        $this->assertArrayHasKey('montant_restant', $response->json('data'));
    }

    public function test_transmettre_facture_envoie_le_mail(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/transmettre")
            ->assertOk();

        Mail::assertSent(FactureMail::class, fn (FactureMail $mail) => $mail->hasTo('client@example.com'));
    }

    public function test_transmettre_facture_interdite_au_collaborateur(): void
    {
        Mail::fake();

        $this->actingAs($this->collaborateur)
            ->postJson("/api/v1/factures/{$this->facture->id}/transmettre")
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_transmettre_facture_sans_adresse_retourne_409(): void
    {
        Mail::fake();
        $this->entreprise->update(['email' => null]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/transmettre")
            ->assertStatus(409);

        Mail::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Avoirs — creation, liste, suppression, autorisation
    // -------------------------------------------------------------------------

    public function test_creer_avoir_reprend_la_tva_et_reference_la_facture(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/avoirs", [
                'montant_ht' => 10000,
                'date_avoir' => date('Y').'-02-01',
                'motif' => 'Correction erreur de facturation',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.numero', 'FA'.date('Y').'-001')
            ->assertJsonPath('data.facture_origine_id', $this->facture->id);

        $data = $response->json('data');
        $this->assertEquals(19, (float) $data['taux_tva_snapshot']);
        $this->assertEquals(1900, (float) $data['montant_tva']);
        $this->assertEquals(11900, (float) $data['montant_ttc']);

        $this->assertDatabaseHas('avoirs', [
            'numero' => 'FA'.date('Y').'-001',
            'facture_origine_id' => $this->facture->id,
        ]);
    }

    public function test_avoir_reduit_le_montant_restant_de_la_facture(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/avoirs", [
                'montant_ht' => 10000,
                'date_avoir' => date('Y').'-02-01',
                'motif' => 'Avoir partiel',
            ])->assertCreated();

        // restant = 112455 - 0 (paye) - 11900 (avoir) = 100555
        $this->assertEquals(100555.0, round($this->facture->fresh()->montantRestant(), 2));
    }

    public function test_avoir_superieur_au_restant_du_est_refuse(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/avoirs", [
                'montant_ht' => 200000,
                'date_avoir' => date('Y').'-02-01',
                'motif' => 'Montant trop eleve',
            ])
            ->assertStatus(409);
    }

    public function test_liste_globale_des_avoirs(): void
    {
        Avoir::create([
            'facture_origine_id' => $this->facture->id,
            'exercice_id' => $this->exercice->id,
            'created_by' => $this->admin->id,
            'numero' => 'FA'.date('Y').'-001',
            'date_avoir' => date('Y').'-02-01',
            'montant_ht' => 10000,
            'taux_tva_snapshot' => 19,
            'montant_tva' => 1900,
            'montant_ttc' => 11900,
            'motif' => 'Correction',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/avoirs');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('FA'.date('Y').'-001', $response->json('data.0.numero'));
    }

    public function test_suppression_avoir_par_admin(): void
    {
        $avoir = Avoir::create([
            'facture_origine_id' => $this->facture->id,
            'exercice_id' => $this->exercice->id,
            'created_by' => $this->admin->id,
            'numero' => 'FA'.date('Y').'-001',
            'date_avoir' => date('Y').'-02-01',
            'montant_ht' => 10000,
            'taux_tva_snapshot' => 19,
            'montant_tva' => 1900,
            'montant_ttc' => 11900,
            'motif' => 'Correction',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/avoirs/{$avoir->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('avoirs', ['id' => $avoir->id]);
    }

    public function test_creation_avoir_interdite_a_la_secretaire(): void
    {
        $this->actingAs($this->secretaire)
            ->postJson("/api/v1/factures/{$this->facture->id}/avoirs", [
                'montant_ht' => 10000,
                'date_avoir' => date('Y').'-02-01',
                'motif' => 'Tentative secretaire',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('avoirs', 0);
    }

    // -------------------------------------------------------------------------
    // Relances — envoi manuel
    // -------------------------------------------------------------------------

    public function test_relance_manuelle_envoie_le_mail_et_persiste_la_relance(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/relances", ['niveau' => 1]);

        $response->assertCreated()
            ->assertJsonPath('data.niveau', 1)
            ->assertJsonPath('data.type', 'manuelle')
            ->assertJsonPath('data.statut', 'envoyee')
            ->assertJsonPath('data.email_destinataire', 'client@example.com');

        Mail::assertSent(RelanceClientMail::class, fn (RelanceClientMail $mail) => $mail->hasTo('client@example.com'));

        $this->assertDatabaseHas('relances', [
            'facture_id' => $this->facture->id,
            'niveau' => 1,
            'type' => 'manuelle',
            'statut' => 'envoyee',
        ]);
    }

    public function test_relance_niveau_invalide_est_rejetee(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/factures/{$this->facture->id}/relances", ['niveau' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['niveau']);

        $this->assertDatabaseCount('relances', 0);
    }
}
