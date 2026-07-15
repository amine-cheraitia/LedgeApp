<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Avoir;
use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\Mission;
use App\Models\Paiement;
use App\Models\Prestation;
use App\Models\Relance;
use App\Models\Setting;
use App\Models\Tache;
use App\Models\TvaTaux;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Couverture ciblee de DashboardService + DashboardController :
 * - GET /api/v1/stats               (admin)        -> getStats
 * - GET /api/v1/stats/secretaire    (secretaire)   -> getSecretaireStats
 * - GET /api/v1/collaborateur/stats (admin|collab) -> getCollaborateurStats
 *
 * Exerce les branches « donnees riches » et « etats vides ».
 */
class DashboardCoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $collaborateur;

    private User $secretaire;

    private Exercice $exercice;

    private Prestation $prestation;

    private TvaTaux $tva;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'collaborateur']);
        Role::firstOrCreate(['name' => 'secretaire']);
        Role::firstOrCreate(['name' => 'client']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->collaborateur = User::factory()->create();
        $this->collaborateur->assignRole('collaborateur');

        $this->secretaire = User::factory()->create();
        $this->secretaire->assignRole('secretaire');

        $annee = (int) now()->year;

        $this->exercice = Exercice::firstOrCreate(
            ['annee' => $annee],
            ['date_ouverture' => $annee.'-01-01', 'statut' => 'ouvert']
        );

        $this->prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            ['designation' => 'Comptabilité', 'tarif_initial' => 120000, 'duree_mois' => 12, 'actif' => true]
        );

        $this->tva = TvaTaux::firstOrCreate(
            ['taux' => 19],
            ['designation' => 'TVA 19%', 'date_debut' => '2020-01-01', 'type' => 'standard', 'actif' => true]
        );

        Setting::updateOrInsert(
            ['key' => 'seuil_alerte_recouvrement'],
            ['value' => '70', 'group' => 'dashboard', 'label' => 'Seuil alerte', 'created_at' => now(), 'updated_at' => now()]
        );

        foreach ([
            ['key' => 'relance_niveau1_jours', 'value' => '15'],
            ['key' => 'relance_niveau2_jours', 'value' => '30'],
            ['key' => 'relance_niveau3_jours', 'value' => '45'],
        ] as $setting) {
            Setting::updateOrInsert(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'group' => 'relances',
                    'label' => $setting['key'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function creerFacture(array $overrides = [], ?Entreprise $entreprise = null): Facture
    {
        $entreprise ??= Entreprise::factory()->create(['statut' => 'client']);

        $mission = Mission::factory()->create([
            'entreprise_id' => $entreprise->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
            'prix_ht' => 100000,
        ]);

        return Facture::factory()->create(array_merge([
            'type' => 'FF',
            'entreprise_id' => $entreprise->id,
            'exercice_id' => $this->exercice->id,
            'mission_id' => $mission->id,
            'created_by' => $this->admin->id,
            'tva_taux_id' => $this->tva->id,
            'montant_ht' => 100000,
            'taux_tva' => 19,
            'montant_tva' => 19000,
            'montant_ttc' => 119000,
            'montant_paye' => 0,
            'statut_paiement' => 'en_attente',
            'date_facture' => now()->startOfMonth()->toDateString(),
            'date_echeance' => now()->addDays(30)->toDateString(),
        ], $overrides));
    }

    private function creerMission(array $overrides = []): Mission
    {
        return Mission::factory()->create(array_merge([
            'entreprise_id' => Entreprise::factory()->create(['statut' => 'client'])->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
            'statut' => 'en_cours',
        ], $overrides));
    }

    private function creerDevis(Entreprise $entreprise, string $statut, float $ttc): Devis
    {
        static $sequence = 0;
        $sequence++;

        return Devis::create([
            'entreprise_id' => $entreprise->id,
            'prestation_id' => $this->prestation->id,
            'exercice_id' => $this->exercice->id,
            'created_by' => $this->admin->id,
            'numero' => 'DV'.now()->year.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            'date_devis' => now()->toDateString(),
            'date_validite' => now()->addDays(30)->toDateString(),
            'prix_ht' => round($ttc / 1.19, 2),
            'montant_ht' => round($ttc / 1.19, 2),
            'taux_tva' => 19,
            'montant_tva' => round($ttc - $ttc / 1.19, 2),
            'montant_ttc' => $ttc,
            'statut' => $statut,
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/stats — getStats (admin)
    // -------------------------------------------------------------------------

    public function test_stats_structure_complete(): void
    {
        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'exercices',
                    'exercice_courant',
                    'kpi' => ['ca_mois', 'tva_collectee', 'taux_recouvrement', 'seuil_recouvrement'],
                    'ca_mensuel' => ['annee', 'data'],
                    'alertes',
                    'entreprises' => ['total', 'clients', 'prospects'],
                    'missions' => ['total', 'en_cours', 'terminees', 'suspendues', 'annulees', 'ca_ht'],
                    'factures' => ['total', 'en_attente', 'partielles', 'soldees', 'ca_ttc', 'total_paye', 'total_impaye', 'en_retard'],
                    'devis' => ['total', 'en_attente', 'acceptes', 'ca_potentiel'],
                    'recentes' => ['factures', 'missions'],
                ],
            ]);

        // Sans filtre exercice -> exercice_courant null, serie ca_mensuel sur 12 mois
        $this->assertNull($res->json('data.exercice_courant'));
        $this->assertCount(12, $res->json('data.ca_mensuel.data'));
        $this->assertSame((int) now()->year, $res->json('data.ca_mensuel.annee'));
    }

    public function test_stats_etat_vide_retourne_zeros(): void
    {
        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $this->assertEquals(0, $res->json('data.kpi.ca_mois'));
        $this->assertEquals(0, $res->json('data.kpi.tva_collectee'));
        $this->assertEquals(0, $res->json('data.kpi.taux_recouvrement'));
        $this->assertSame(0, $res->json('data.factures.total'));
        $this->assertEquals(0, $res->json('data.factures.ca_ttc'));
        $this->assertEquals(0, $res->json('data.factures.total_impaye'));
        $this->assertSame(0, $res->json('data.missions.total'));
        $this->assertEmpty($res->json('data.alertes'));
        $this->assertEquals(array_fill(0, 12, 0), $res->json('data.ca_mensuel.data'));
    }

    public function test_stats_donnees_riches_compte_factures_et_missions(): void
    {
        // Factures : en_attente, partielle, soldee
        $this->creerFacture(['statut_paiement' => 'en_attente', 'montant_paye' => 0, 'montant_ttc' => 100000]);
        $this->creerFacture(['statut_paiement' => 'partiel', 'montant_paye' => 40000, 'montant_ttc' => 100000]);
        $this->creerFacture(['statut_paiement' => 'solde', 'montant_paye' => 100000, 'montant_ttc' => 100000]);

        // Missions : en_cours, terminee, suspendue, annulee
        $this->creerMission(['statut' => 'en_cours', 'prix_ht' => 100000]);
        $this->creerMission(['statut' => 'terminee', 'prix_ht' => 200000]);
        $this->creerMission(['statut' => 'suspendue']);
        $this->creerMission(['statut' => 'annulee']);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $this->assertSame(3, $res->json('data.factures.total'));
        $this->assertSame(1, $res->json('data.factures.en_attente'));
        $this->assertSame(1, $res->json('data.factures.partielles'));
        $this->assertSame(1, $res->json('data.factures.soldees'));
        $this->assertEquals(300000.0, $res->json('data.factures.ca_ttc'));
        $this->assertEquals(140000.0, $res->json('data.factures.total_paye'));
        // impaye = (100000-0) + (100000-40000) = 160000
        $this->assertEquals(160000.0, $res->json('data.factures.total_impaye'));

        // 3 missions via creerFacture (en_cours par defaut) + 4 explicites = 7
        $this->assertSame(7, $res->json('data.missions.total'));
        $this->assertSame(1, $res->json('data.missions.terminees'));
        $this->assertSame(1, $res->json('data.missions.suspendues'));
        $this->assertSame(1, $res->json('data.missions.annulees'));
    }

    public function test_stats_ca_mois_et_tva_collectee(): void
    {
        $this->creerFacture(['montant_ttc' => 119000, 'montant_tva' => 19000, 'date_facture' => now()->startOfMonth()->toDateString()]);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $this->assertEquals(119000.0, $res->json('data.kpi.ca_mois'));
        $this->assertEquals(19000.0, $res->json('data.kpi.tva_collectee'));
    }

    public function test_stats_ca_mensuel_reparti_par_mois(): void
    {
        // Janvier de l'annee courante
        $this->creerFacture([
            'montant_ttc' => 50000,
            'date_facture' => now()->startOfYear()->toDateString(),
        ]);
        // Mars de l'annee courante
        $this->creerFacture([
            'montant_ttc' => 30000,
            'date_facture' => now()->startOfYear()->addMonths(2)->toDateString(),
        ]);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $data = $res->json('data.ca_mensuel.data');
        $this->assertEquals(50000.0, $data[0]);  // janvier (index 0)
        $this->assertEquals(30000.0, $data[2]);  // mars (index 2)
        $this->assertSame((int) now()->year, $res->json('data.ca_mensuel.annee'));
    }

    public function test_stats_filtre_par_exercice_isole_les_donnees(): void
    {
        $annePrec = (int) now()->year - 1;
        $exercicePrecedent = Exercice::firstOrCreate(
            ['annee' => $annePrec],
            ['date_ouverture' => $annePrec.'-01-01', 'statut' => 'cloture']
        );

        // Facture dans l'exercice precedent
        $this->creerFacture([
            'exercice_id' => $exercicePrecedent->id,
            'montant_ttc' => 50000,
            'date_facture' => $annePrec.'-06-15',
        ]);
        // Facture dans l'exercice courant
        $this->creerFacture([
            'exercice_id' => $this->exercice->id,
            'montant_ttc' => 80000,
        ]);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats?exercice_id='.$this->exercice->id)
            ->assertOk();

        $this->assertSame($this->exercice->id, $res->json('data.exercice_courant'));
        $this->assertEquals(80000.0, $res->json('data.factures.ca_ttc'));

        // ca_mensuel indexe sur l'annee de l'exercice filtre
        $res2 = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats?exercice_id='.$exercicePrecedent->id)
            ->assertOk();

        $this->assertSame($annePrec, $res2->json('data.ca_mensuel.annee'));
        $this->assertEquals(50000.0, $res2->json('data.factures.ca_ttc'));
    }

    public function test_stats_alerte_facture_en_retard(): void
    {
        $this->creerFacture([
            'statut_paiement' => 'en_attente',
            'date_echeance' => now()->subDays(10)->toDateString(),
        ]);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $this->assertSame(1, $res->json('data.factures.en_retard'));
        $danger = collect($res->json('data.alertes'))->firstWhere('type', 'danger');
        $this->assertNotNull($danger);
        $this->assertStringContainsString('retard', $danger['message']);
    }

    public function test_stats_alerte_taux_recouvrement_faible(): void
    {
        // Taux = 50% < seuil 70%
        $this->creerFacture([
            'montant_ttc' => 100000,
            'montant_paye' => 50000,
            'statut_paiement' => 'partiel',
            'date_echeance' => now()->addDays(30)->toDateString(),
        ]);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $warn = collect($res->json('data.alertes'))->firstWhere('type', 'warn');
        $this->assertNotNull($warn);
        $this->assertStringContainsString('recouvrement', $warn['message']);
    }

    public function test_stats_entreprises_et_devis_comptes(): void
    {
        Entreprise::factory()->count(2)->create(['statut' => 'client']);
        Entreprise::factory()->count(3)->create(['statut' => 'prospect']);

        $entreprise = Entreprise::factory()->create(['statut' => 'client']);
        $this->creerDevis($entreprise, 'brouillon', 60000);
        $this->creerDevis($entreprise, 'envoye', 40000);
        $this->creerDevis($entreprise, 'accepte', 90000);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $this->assertGreaterThanOrEqual(2, $res->json('data.entreprises.clients'));
        $this->assertGreaterThanOrEqual(3, $res->json('data.entreprises.prospects'));
        $this->assertSame(3, $res->json('data.devis.total'));
        $this->assertSame(2, $res->json('data.devis.en_attente'));
        $this->assertSame(1, $res->json('data.devis.acceptes'));
        // ca_potentiel = brouillon + envoye = 60000 + 40000
        $this->assertEquals(100000.0, $res->json('data.devis.ca_potentiel'));
    }

    public function test_stats_devis_scopes_par_exercice(): void
    {
        $anneePrec = (int) now()->year - 1;
        $exercicePrecedent = Exercice::firstOrCreate(
            ['annee' => $anneePrec],
            ['date_ouverture' => $anneePrec.'-01-01', 'statut' => 'cloture']
        );

        $entreprise = Entreprise::factory()->create(['statut' => 'client']);

        // Devis dans l'exercice precedent -> ne doit pas etre compte quand on filtre l'exercice courant
        $devisPrecedent = $this->creerDevis($entreprise, 'envoye', 70000);
        $devisPrecedent->update(['exercice_id' => $exercicePrecedent->id]);

        // Devis dans l'exercice courant
        $this->creerDevis($entreprise, 'brouillon', 60000);
        $this->creerDevis($entreprise, 'accepte', 90000);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats?exercice_id='.$this->exercice->id)
            ->assertOk();

        $this->assertSame(2, $res->json('data.devis.total'));
        $this->assertSame(1, $res->json('data.devis.en_attente'));
        $this->assertSame(1, $res->json('data.devis.acceptes'));
        $this->assertEquals(60000.0, $res->json('data.devis.ca_potentiel'));
    }

    public function test_stats_recentes_limitees_a_cinq(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->creerFacture(['date_facture' => now()->subDays($i)->toDateString()]);
        }

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $this->assertLessThanOrEqual(5, count($res->json('data.recentes.factures')));
        $this->assertLessThanOrEqual(5, count($res->json('data.recentes.missions')));
    }

    public function test_stats_seuil_recouvrement_depuis_setting(): void
    {
        Setting::updateOrInsert(
            ['key' => 'seuil_alerte_recouvrement'],
            ['value' => '85', 'group' => 'dashboard', 'label' => 'Seuil alerte', 'created_at' => now(), 'updated_at' => now()]
        );

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $this->assertEquals(85.0, $res->json('data.kpi.seuil_recouvrement'));
    }

    public function test_stats_refuse_aux_roles_non_admin(): void
    {
        // Non authentifie d'abord (actingAs persiste dans le meme test)
        $this->getJson('/api/v1/stats')->assertUnauthorized();
        $this->actingAs($this->secretaire)->getJson('/api/v1/stats')->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/stats/secretaire — getSecretaireStats
    // -------------------------------------------------------------------------

    public function test_secretaire_structure_et_etat_vide(): void
    {
        $res = $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats/secretaire')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'alertes',
                    'actions',
                    'creances' => ['total_impaye', 'clients_debiteurs', 'en_retard'],
                    'aging' => ['retard_15_30', 'retard_30_60', 'retard_60_plus'],
                    'relances_dues' => ['niveau_1', 'niveau_2', 'niveau_3'],
                    'top_debiteurs',
                    'encaissements_mois' => ['count', 'montant'],
                    'recentes_creances',
                ],
            ]);

        $this->assertEquals(0, $res->json('data.creances.total_impaye'));
        $this->assertSame(0, $res->json('data.creances.clients_debiteurs'));
        $this->assertSame(0, $res->json('data.creances.en_retard'));
        $this->assertEquals(0, $res->json('data.aging.retard_15_30'));
        $this->assertSame(0, $res->json('data.relances_dues.niveau_1'));
        $this->assertEmpty($res->json('data.top_debiteurs'));
        $this->assertEmpty($res->json('data.alertes'));
        $this->assertEmpty($res->json('data.actions'));
        $this->assertSame(0, $res->json('data.encaissements_mois.count'));
    }

    public function test_secretaire_aging_par_tranches(): void
    {
        // 20 jours de retard -> tranche 15-30
        $this->creerFacture([
            'montant_ttc' => 50000,
            'statut_paiement' => 'en_attente',
            'date_echeance' => now()->subDays(20)->toDateString(),
        ]);
        // 45 jours -> tranche 30-60
        $this->creerFacture([
            'montant_ttc' => 70000,
            'statut_paiement' => 'en_attente',
            'date_echeance' => now()->subDays(45)->toDateString(),
        ]);
        // 90 jours -> tranche 60+
        $this->creerFacture([
            'montant_ttc' => 90000,
            'statut_paiement' => 'en_attente',
            'date_echeance' => now()->subDays(90)->toDateString(),
        ]);

        $res = $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats/secretaire')
            ->assertOk();

        $this->assertEquals(50000.0, $res->json('data.aging.retard_15_30'));
        $this->assertEquals(70000.0, $res->json('data.aging.retard_30_60'));
        $this->assertEquals(90000.0, $res->json('data.aging.retard_60_plus'));
        $this->assertSame(3, $res->json('data.creances.en_retard'));
        $this->assertEquals(210000.0, $res->json('data.creances.total_impaye'));
    }

    public function test_secretaire_top_debiteurs_tries_par_montant(): void
    {
        $gros = Entreprise::factory()->create(['statut' => 'client', 'raison_sociale' => 'Gros Debiteur SARL']);
        $petit = Entreprise::factory()->create(['statut' => 'client', 'raison_sociale' => 'Petit Debiteur SARL']);

        // Gros : deux factures impayees
        $this->creerFacture(['montant_ttc' => 100000, 'statut_paiement' => 'en_attente'], $gros);
        $this->creerFacture(['montant_ttc' => 80000, 'statut_paiement' => 'en_attente'], $gros);
        // Petit : une facture
        $this->creerFacture(['montant_ttc' => 30000, 'statut_paiement' => 'en_attente'], $petit);

        $res = $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats/secretaire')
            ->assertOk();

        $top = $res->json('data.top_debiteurs');
        $this->assertCount(2, $top);
        $this->assertSame($gros->id, $top[0]['entreprise_id']);
        $this->assertEquals(180000.0, $top[0]['montant_impaye']);
        $this->assertSame('Gros Debiteur SARL', $top[0]['raison_sociale']);
        $this->assertSame($petit->id, $top[1]['entreprise_id']);
        $this->assertSame(2, $res->json('data.creances.clients_debiteurs'));
    }

    public function test_secretaire_facture_restant_nul_exclue_des_debiteurs(): void
    {
        // Facture en_attente entierement couverte par un avoir -> montantRestant() == 0
        $facture = $this->creerFacture([
            'montant_ttc' => 100000,
            'montant_paye' => 0,
            'statut_paiement' => 'en_attente',
        ]);

        Avoir::create([
            'facture_origine_id' => $facture->id,
            'entreprise_id' => $facture->entreprise_id,
            'exercice_id' => $this->exercice->id,
            'created_by' => $this->admin->id,
            'numero' => 'FA'.now()->year.'-001',
            'type' => 'FA',
            'date_avoir' => now()->toDateString(),
            'montant_ht' => 100000,
            'montant_ttc' => 100000,
            'motif' => 'Annulation totale',
        ]);

        $res = $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats/secretaire')
            ->assertOk();

        $this->assertEquals(0, $res->json('data.creances.total_impaye'));
        $this->assertEmpty($res->json('data.top_debiteurs'));
        // La facture reste dans la liste des creances -> clients_debiteurs la compte
        $this->assertSame(1, $res->json('data.creances.clients_debiteurs'));
    }

    public function test_secretaire_encaissements_du_mois(): void
    {
        $facture = $this->creerFacture(['statut_paiement' => 'partiel', 'montant_paye' => 50000]);

        Paiement::create([
            'facture_id' => $facture->id,
            'recorded_by' => $this->admin->id,
            'montant' => 50000,
            'date_paiement' => now()->toDateString(),
            'mode_paiement' => 'virement',
        ]);
        // Paiement d'un autre mois -> non compte
        Paiement::create([
            'facture_id' => $facture->id,
            'recorded_by' => $this->admin->id,
            'montant' => 12000,
            'date_paiement' => now()->subMonthsNoOverflow(2)->toDateString(),
            'mode_paiement' => 'virement',
        ]);

        $res = $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats/secretaire')
            ->assertOk();

        $this->assertSame(1, $res->json('data.encaissements_mois.count'));
        $this->assertEquals(50000.0, $res->json('data.encaissements_mois.montant'));
    }

    public function test_secretaire_alertes_et_worklist(): void
    {
        // Facture en retard -> alerte danger + action factures_retard
        $this->creerFacture([
            'montant_ttc' => 60000,
            'statut_paiement' => 'en_attente',
            'date_echeance' => now()->subDays(20)->toDateString(),
        ]);

        $res = $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats/secretaire')
            ->assertOk();

        $types = collect($res->json('data.alertes'))->pluck('type');
        $this->assertContains('danger', $types);

        $actions = collect($res->json('data.actions'));
        $this->assertContains('factures_retard', $actions->pluck('key'));
        // Worklist triee par severite (danger avant warn)
        $this->assertSame('danger', $actions->first()['severity']);
    }

    public function test_secretaire_relances_dues_par_niveau(): void
    {
        // Delais : niveau1=15, niveau2=30, niveau3=45

        // 20j de retard, aucune relance -> niveau 1 attendu
        $this->creerFacture([
            'montant_ttc' => 40000,
            'statut_paiement' => 'en_attente',
            'date_echeance' => now()->subDays(20)->toDateString(),
        ]);

        // 40j, relance niveau 1 deja envoyee -> niveau 2 attendu (precedent OK)
        $fNiveau2 = $this->creerFacture([
            'montant_ttc' => 40000,
            'statut_paiement' => 'en_attente',
            'date_echeance' => now()->subDays(40)->toDateString(),
        ]);
        Relance::create([
            'facture_id' => $fNiveau2->id,
            'niveau' => 1,
            'type' => 'automatique',
            'email_destinataire' => 'a@b.dz',
            'envoyee_le' => now()->subDays(10),
            'statut' => 'envoyee',
        ]);

        // 20j, relance niveau 1 deja envoyee -> deja traitee, on saute (dejaEnvoyee)
        $fDeja = $this->creerFacture([
            'montant_ttc' => 40000,
            'statut_paiement' => 'en_attente',
            'date_echeance' => now()->subDays(20)->toDateString(),
        ]);
        Relance::create([
            'facture_id' => $fDeja->id,
            'niveau' => 1,
            'type' => 'automatique',
            'email_destinataire' => 'a@b.dz',
            'envoyee_le' => now()->subDays(5),
            'statut' => 'envoyee',
        ]);

        // 40j SANS relance niveau 1 -> niveau 2 attendu mais precedent absent -> on saute
        $this->creerFacture([
            'montant_ttc' => 40000,
            'statut_paiement' => 'en_attente',
            'date_echeance' => now()->subDays(40)->toDateString(),
        ]);

        // 10j de retard -> en dessous du 1er palier -> niveau null -> on saute
        $this->creerFacture([
            'montant_ttc' => 40000,
            'statut_paiement' => 'en_attente',
            'date_echeance' => now()->subDays(10)->toDateString(),
        ]);

        // Sans echeance -> ignoree par le calcul des relances
        $this->creerFacture([
            'montant_ttc' => 40000,
            'statut_paiement' => 'en_attente',
            'date_echeance' => null,
        ]);

        $res = $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats/secretaire')
            ->assertOk();

        $relances = $res->json('data.relances_dues');
        $this->assertSame(1, $relances['niveau_1']);
        $this->assertSame(1, $relances['niveau_2']);
        $this->assertSame(0, $relances['niveau_3']);

        // Une alerte « relances dues » doit apparaitre
        $types = collect($res->json('data.alertes'))->pluck('type');
        $this->assertContains('warn', $types);
        $this->assertContains('relances', collect($res->json('data.actions'))->pluck('key'));
    }

    public function test_secretaire_recentes_creances_limitees(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->creerFacture([
                'montant_ttc' => 10000 + $i,
                'statut_paiement' => 'en_attente',
                'date_echeance' => now()->addDays($i)->toDateString(),
            ]);
        }

        $res = $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats/secretaire')
            ->assertOk();

        $this->assertLessThanOrEqual(5, count($res->json('data.recentes_creances')));
    }

    public function test_secretaire_refuse_aux_autres_roles(): void
    {
        $this->getJson('/api/v1/stats/secretaire')->assertUnauthorized();
        $this->actingAs($this->admin)->getJson('/api/v1/stats/secretaire')->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/collaborateur/stats — getCollaborateurStats
    // -------------------------------------------------------------------------

    public function test_collaborateur_structure_et_etat_vide(): void
    {
        // Collaborateur sans mission ni tache
        $res = $this->actingAs($this->collaborateur)
            ->getJson('/api/v1/collaborateur/stats')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'missions' => ['total', 'en_cours', 'terminees'],
                    'taches' => ['total', 'a_faire', 'en_cours', 'terminees', 'bloquees', 'taux_completion', 'en_retard'],
                    'mes_missions',
                    'mes_taches_urgentes',
                    'echeances',
                    'activite_recente',
                ],
            ]);

        $this->assertSame(0, $res->json('data.missions.total'));
        $this->assertSame(0, $res->json('data.taches.total'));
        $this->assertSame(0, $res->json('data.taches.taux_completion'));
        $this->assertEmpty($res->json('data.mes_missions'));
        $this->assertEmpty($res->json('data.mes_taches_urgentes'));
        $this->assertEmpty($res->json('data.echeances'));
        $this->assertEmpty($res->json('data.activite_recente'));
    }

    public function test_collaborateur_donnees_riches(): void
    {
        // Mission A : en cours, echeance proche, 2 taches (1 terminee, 1 a_faire)
        $missionA = $this->creerMission([
            'statut' => 'en_cours',
            'date_fin' => now()->addDays(10)->toDateString(),
        ]);
        $missionA->collaborateurs()->attach($this->collaborateur->id);

        // Mission B : terminee
        $missionB = $this->creerMission(['statut' => 'terminee']);
        $missionB->collaborateurs()->attach($this->collaborateur->id);

        // Taches assignees au collaborateur
        // t1 : terminee (mission A) -> alimente terminees + activite_recente
        Tache::factory()->terminee()->create([
            'mission_id' => $missionA->id,
            'assigned_to' => $this->collaborateur->id,
            'titre' => 'Tache terminee',
        ]);
        // t2 : a_faire (mission A), echeance dans 7 jours -> echeances + mes_taches_urgentes
        Tache::factory()->create([
            'mission_id' => $missionA->id,
            'assigned_to' => $this->collaborateur->id,
            'statut' => 'a_faire',
            'titre' => 'Tache a faire',
            'date_echeance' => now()->addDays(7)->toDateString(),
        ]);
        // t3 : en_cours (mission B), echeance passee -> en_retard + mes_taches_urgentes
        Tache::factory()->create([
            'mission_id' => $missionB->id,
            'assigned_to' => $this->collaborateur->id,
            'statut' => 'en_cours',
            'titre' => 'Tache en retard',
            'date_echeance' => now()->subDays(3)->toDateString(),
        ]);
        // t4 : bloquee (mission B)
        Tache::factory()->create([
            'mission_id' => $missionB->id,
            'assigned_to' => $this->collaborateur->id,
            'statut' => 'bloquee',
            'titre' => 'Tache bloquee',
        ]);

        $res = $this->actingAs($this->collaborateur)
            ->getJson('/api/v1/collaborateur/stats')
            ->assertOk();

        // Missions
        $this->assertSame(2, $res->json('data.missions.total'));
        $this->assertSame(1, $res->json('data.missions.en_cours'));
        $this->assertSame(1, $res->json('data.missions.terminees'));

        // Taches
        $this->assertSame(4, $res->json('data.taches.total'));
        $this->assertSame(1, $res->json('data.taches.a_faire'));
        $this->assertSame(1, $res->json('data.taches.en_cours'));
        $this->assertSame(1, $res->json('data.taches.terminees'));
        $this->assertSame(1, $res->json('data.taches.bloquees'));
        $this->assertEquals(25.0, $res->json('data.taches.taux_completion'));
        $this->assertSame(1, $res->json('data.taches.en_retard'));

        // Listes derivees
        $this->assertNotEmpty($res->json('data.mes_missions'));
        $this->assertNotEmpty($res->json('data.mes_taches_urgentes'));
        $this->assertNotEmpty($res->json('data.echeances'));
        $this->assertNotEmpty($res->json('data.activite_recente'));

        // La tache en_cours en retard est marquee et priorisee
        $urgentes = collect($res->json('data.mes_taches_urgentes'));
        $enRetard = $urgentes->firstWhere('statut', 'en_cours');
        $this->assertNotNull($enRetard);
        $this->assertTrue($enRetard['en_retard']);

        // La mission A (2 taches, 1 terminee) a une progression de 50%
        $missionApayload = collect($res->json('data.mes_missions'))->firstWhere('id', $missionA->id);
        $this->assertNotNull($missionApayload);
        $this->assertEquals(2, $missionApayload['taches_total']);
        $this->assertEquals(1, $missionApayload['taches_terminees']);
        $this->assertEquals(50, $missionApayload['progression']);
    }

    public function test_collaborateur_ne_voit_pas_les_donnees_des_autres(): void
    {
        // Mission + tache appartenant a un AUTRE collaborateur
        $autre = User::factory()->create();
        $autre->assignRole('collaborateur');

        $mission = $this->creerMission(['statut' => 'en_cours']);
        $mission->collaborateurs()->attach($autre->id);
        Tache::factory()->create([
            'mission_id' => $mission->id,
            'assigned_to' => $autre->id,
            'statut' => 'a_faire',
        ]);

        $res = $this->actingAs($this->collaborateur)
            ->getJson('/api/v1/collaborateur/stats')
            ->assertOk();

        $this->assertSame(0, $res->json('data.missions.total'));
        $this->assertSame(0, $res->json('data.taches.total'));
    }

    public function test_collaborateur_stats_accessible_admin(): void
    {
        // Route ouverte a admin|collaborateur
        $this->actingAs($this->admin)
            ->getJson('/api/v1/collaborateur/stats')
            ->assertOk();
    }

    public function test_collaborateur_stats_refuse_secretaire_et_client(): void
    {
        $this->getJson('/api/v1/collaborateur/stats')->assertUnauthorized();

        $this->actingAs($this->secretaire)
            ->getJson('/api/v1/collaborateur/stats')
            ->assertForbidden();

        $client = User::factory()->create(['entreprise_id' => Entreprise::factory()->create()->id]);
        $client->assignRole('client');
        $this->actingAs($client)
            ->getJson('/api/v1/collaborateur/stats')
            ->assertForbidden();
    }
}
