<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Avoir;
use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\FactureLigne;
use App\Models\Mission;
use App\Models\Paiement;
use App\Models\Prestation;
use App\Models\Setting;
use App\Models\Tache;
use App\Models\TacheCommentaire;
use App\Models\TvaTaux;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Couverture des endpoints de generation PDF (PdfService).
 * On verifie le flux reussi (statut 200 + Content-Type application/pdf) et
 * quelques cas d'acces refuse selon le role. Le binaire n'est jamais compare.
 */
class PdfEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    private Entreprise $entreprise;

    private Exercice $exercice;

    private Prestation $prestation;

    private Mission $mission;

    private Facture $facture;

    private Devis $devis;

    private Avoir $avoir;

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
            'statut' => 'client',
            'raison_sociale' => 'ACME Conseil',
            'email' => 'client@example.com',
            'adresse' => '12 rue des Freres, Alger',
            'ville' => 'Alger',
            'nif' => '000116001234567',
            'nis' => '000116007654321',
        ]);

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
            'date_debut' => '2020-01-01',
            'type' => 'standard',
            'actif' => true,
        ]);

        // Parametres cabinet lus par PdfService::getCabinetInfo() + les Blade PDF.
        Setting::set('cabinet_nom', 'Cabinet Ledge');
        Setting::set('cabinet_soustitre', 'Experts-comptables');
        Setting::set('cabinet_adresse', '5 boulevard Zighout Youcef, Alger');
        Setting::set('cabinet_nif', '000216009999999');
        Setting::set('cabinet_nis', '000216008888888');
        Setting::set('cabinet_rib', '00300123456789012345');
        Setting::set('cabinet_telephone', '+213 21 00 00 00');
        Setting::set('cabinet_agrement', 'AG-2020-001');
        Setting::set('cabinet_ville', 'Alger');
        Setting::set('devis_prefixe', 'DV');
        Setting::set('facture_prefixe', 'FF');
        Setting::set('avoir_prefixe', 'FA');

        $this->mission = Mission::create([
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'exercice_id' => $this->exercice->id,
            'reference' => 'M'.date('Y').'-001',
            'prix_ht' => 315000,
            'date_debut' => date('Y').'-01-01',
            'date_fin' => date('Y').'-12-31',
            'statut' => 'en_cours',
            'visible_portail' => true,
            'convention_numero' => 'CV'.date('Y').'-001',
            'mandat_numero' => 'MD'.date('Y').'-001',
            'notes' => 'Mission de suivi comptable annuel.',
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
            'mode_paiement' => 'virement',
        ]);

        FactureLigne::create([
            'facture_id' => $this->facture->id,
            'prestation_id' => $this->prestation->id,
            'designation' => 'Accompagnement comptable — 30%',
            'quantite' => 1,
            'prix_unitaire_ht' => 94500,
            'total_ht' => 94500,
            'ordre' => 1,
        ]);

        $this->devis = Devis::create([
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'exercice_id' => $this->exercice->id,
            'created_by' => $this->admin->id,
            'tva_taux_id' => $tvaTaux->id,
            'numero' => 'DV'.date('Y').'-001',
            'date_devis' => date('Y').'-01-05',
            'date_validite' => date('Y').'-02-05',
            'prix_ht' => 315000,
            'montant_ht' => 315000,
            'taux_tva' => 19,
            'montant_tva' => 59850,
            'montant_ttc' => 374850,
            'statut' => 'brouillon',
        ]);

        $this->avoir = Avoir::create([
            'facture_origine_id' => $this->facture->id,
            'exercice_id' => $this->exercice->id,
            'created_by' => $this->admin->id,
            'numero' => 'FA'.date('Y').'-001',
            'date_avoir' => date('Y').'-02-01',
            'montant_ht' => 10000,
            'taux_tva_snapshot' => 19,
            'montant_tva' => 1900,
            'montant_ttc' => 11900,
            'motif' => 'Correction erreur de facturation',
        ]);

        $this->client = User::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'portail_actif' => true,
        ]);
        $this->client->assignRole('client');
    }

    // -------------------------------------------------------------------------
    // Back-office — flux reussi
    // -------------------------------------------------------------------------

    public function test_devis_pdf_est_genere(): void
    {
        $this->actingAs($this->admin)
            ->get("/api/v1/devis/{$this->devis->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_facture_pdf_est_genere(): void
    {
        $this->actingAs($this->admin)
            ->get("/api/v1/factures/{$this->facture->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_avoir_pdf_est_genere(): void
    {
        $this->actingAs($this->admin)
            ->get("/api/v1/factures/{$this->facture->id}/avoirs/{$this->avoir->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_avoir_pdf_404_si_facture_ne_correspond_pas(): void
    {
        $autreFacture = Facture::create([
            'entreprise_id' => $this->entreprise->id,
            'exercice_id' => $this->exercice->id,
            'mission_id' => $this->mission->id,
            'created_by' => $this->admin->id,
            'tva_taux_id' => $this->facture->tva_taux_id,
            'numero' => 'FF'.date('Y').'-002',
            'type' => 'FF',
            'date_facture' => date('Y').'-02-15',
            'date_echeance' => date('Y').'-04-01',
            'montant_ht' => 94500,
            'taux_tva' => 19,
            'montant_tva' => 17955,
            'montant_ttc' => 112455,
            'montant_paye' => 0,
            'statut_paiement' => 'en_attente',
            'mode_paiement' => 'non_defini',
        ]);

        // L'avoir appartient a $this->facture, pas a $autreFacture -> 404.
        $this->actingAs($this->admin)
            ->getJson("/api/v1/factures/{$autreFacture->id}/avoirs/{$this->avoir->id}/pdf")
            ->assertNotFound();
    }

    public function test_mission_rapport_pdf_est_genere(): void
    {
        // Enrichit la mission (taches + commentaires + paiement) pour exercer
        // les branches d'agregation du rapport de mission.
        $tache = Tache::factory()->create([
            'mission_id' => $this->mission->id,
            'statut' => 'terminee',
        ]);
        TacheCommentaire::create([
            'tache_id' => $tache->id,
            'user_id' => $this->admin->id,
            'contenu' => 'Cloture de la tache.',
            'visible_portail' => true,
        ]);
        Paiement::create([
            'facture_id' => $this->facture->id,
            'recorded_by' => $this->admin->id,
            'montant' => 50000,
            'date_paiement' => date('Y').'-02-10',
            'mode_paiement' => 'virement',
        ]);

        $this->actingAs($this->admin)
            ->get("/api/v1/missions/{$this->mission->id}/rapport/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_mission_convention_pdf_est_genere(): void
    {
        $this->actingAs($this->admin)
            ->get("/api/v1/missions/{$this->mission->id}/convention/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_mission_mandat_pdf_est_genere(): void
    {
        $this->actingAs($this->admin)
            ->get("/api/v1/missions/{$this->mission->id}/mandat/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_exercice_rapport_cloture_pdf_est_genere(): void
    {
        // Seconde facture soldee sur un autre mois : exerce l'agregation
        // mensuelle et l'exclusion des factures soldees de la liste des impayes.
        Facture::create([
            'entreprise_id' => $this->entreprise->id,
            'exercice_id' => $this->exercice->id,
            'mission_id' => $this->mission->id,
            'created_by' => $this->admin->id,
            'tva_taux_id' => $this->facture->tva_taux_id,
            'numero' => 'FF'.date('Y').'-003',
            'type' => 'FF',
            'date_facture' => date('Y').'-06-20',
            'date_echeance' => date('Y').'-08-01',
            'montant_ht' => 126000,
            'taux_tva' => 19,
            'montant_tva' => 23940,
            'montant_ttc' => 149940,
            'montant_paye' => 149940,
            'statut_paiement' => 'solde',
            'mode_paiement' => 'virement',
        ]);

        $this->actingAs($this->admin)
            ->get("/api/v1/exercices/{$this->exercice->id}/rapport-cloture/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    // -------------------------------------------------------------------------
    // Portail client — flux reussi
    // -------------------------------------------------------------------------

    public function test_portail_mission_rapport_pdf_est_genere_pour_le_client(): void
    {
        // Commentaire interne (non visible portail) + commentaire visible :
        // exerce le filtrage portailMode de genererRapportMission.
        $tache = Tache::factory()->create(['mission_id' => $this->mission->id]);
        TacheCommentaire::create([
            'tache_id' => $tache->id,
            'user_id' => $this->admin->id,
            'contenu' => 'Note interne confidentielle.',
            'visible_portail' => false,
        ]);
        TacheCommentaire::create([
            'tache_id' => $tache->id,
            'user_id' => $this->admin->id,
            'contenu' => 'Point d\'avancement partage.',
            'visible_portail' => true,
        ]);

        $this->actingAs($this->client)
            ->get("/api/v1/portail/missions/{$this->mission->id}/rapport/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    // -------------------------------------------------------------------------
    // Acces refuse selon le role
    // -------------------------------------------------------------------------

    public function test_client_ne_peut_pas_telecharger_le_pdf_facture_backoffice(): void
    {
        // Le middleware backoffice bloque les comptes client (403).
        $this->actingAs($this->client)
            ->getJson("/api/v1/factures/{$this->facture->id}/pdf")
            ->assertForbidden();
    }

    public function test_secretaire_ne_peut_pas_telecharger_le_rapport_mission(): void
    {
        // Les routes missions sont reservees admin|collaborateur : la secretaire est exclue.
        $secretaire = User::factory()->create();
        $secretaire->assignRole('secretaire');

        $this->actingAs($secretaire)
            ->getJson("/api/v1/missions/{$this->mission->id}/rapport/pdf")
            ->assertForbidden();
    }

    public function test_collaborateur_ne_peut_pas_telecharger_le_rapport_cloture(): void
    {
        // Le rapport de cloture d'exercice est reserve a l'admin.
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');

        $this->actingAs($collaborateur)
            ->getJson("/api/v1/exercices/{$this->exercice->id}/rapport-cloture/pdf")
            ->assertForbidden();
    }

    public function test_client_ne_peut_pas_telecharger_le_rapport_dune_autre_entreprise(): void
    {
        $autreEntreprise = Entreprise::factory()->create(['statut' => 'client']);
        $autreMission = Mission::create([
            'entreprise_id' => $autreEntreprise->id,
            'prestation_id' => $this->prestation->id,
            'exercice_id' => $this->exercice->id,
            'reference' => 'M'.date('Y').'-999',
            'prix_ht' => 200000,
            'date_debut' => date('Y').'-01-01',
            'date_fin' => date('Y').'-12-31',
            'statut' => 'en_cours',
            'visible_portail' => true,
        ]);

        $this->actingAs($this->client)
            ->getJson("/api/v1/portail/missions/{$autreMission->id}/rapport/pdf")
            ->assertForbidden();
    }

    public function test_pdf_facture_requiert_une_authentification(): void
    {
        $this->getJson("/api/v1/factures/{$this->facture->id}/pdf")
            ->assertUnauthorized();
    }
}
