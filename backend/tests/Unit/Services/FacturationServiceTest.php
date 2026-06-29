<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\CategorieEntreprise;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Mission;
use App\Models\Paiement;
use App\Models\Prestation;
use App\Models\RegimeFiscal;
use App\Models\TvaTaux;
use App\Models\User;
use App\Services\FacturationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FacturationServiceTest extends TestCase
{
    use RefreshDatabase;

    private FacturationService $service;

    private Exercice $exercice;

    private Entreprise $entreprise;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);

        $this->service = app(FacturationService::class);

        $this->exercice = Exercice::factory()->create(['annee' => 2026, 'statut' => 'ouvert']);

        RegimeFiscal::create(['code' => 'reel', 'designation' => 'Réel', 'indice' => 1.5]);
        CategorieEntreprise::create(['code' => 'PME', 'designation' => 'PME', 'indice' => 1.75]);

        $this->entreprise = Entreprise::factory()->create([
            'statut' => 'client',
            'regime_fiscal' => 'reel',
            'categorie' => 'PME',
        ]);

        $prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            ['designation' => 'Accompagnement comptable', 'tarif_initial' => 120000, 'duree_mois' => 12]
        );

        $this->mission = Mission::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $prestation->id,
            'prix_ht' => 315000,
            'statut' => 'en_cours',
        ]);

        TvaTaux::create([
            'taux' => 19,
            'designation' => 'TVA standard',
            'date_debut' => '2023-01-01',
            'date_fin' => null,
            'type' => 'standard',
            'actif' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Numérotation séquentielle
    // -------------------------------------------------------------------------

    public function test_generer_numero_premier_document(): void
    {
        $numero = $this->service->genererNumero('FF', 'factures', $this->exercice);

        $this->assertEquals('FF2026-001', $numero);
    }

    public function test_generer_numero_sequentiel(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Créer de vraies factures pour que le compteur incrémente en BDD
        $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-01-10'], $admin->id);
        $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-02-10'], $admin->id);
        $facture3 = $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-03-10'], $admin->id);

        $this->assertEquals('FF2026-003', $facture3->numero);
    }

    public function test_numerotation_independante_par_prefixe(): void
    {
        $this->service->genererNumero('FF', 'factures', $this->exercice);
        $numero = $this->service->genererNumero('FA', 'avoirs', $this->exercice);

        $this->assertEquals('FA2026-001', $numero);
    }

    public function test_numerotation_reset_par_exercice(): void
    {
        $exercice2025 = Exercice::factory()->create(['annee' => 2025, 'statut' => 'cloture']);
        $exercice2026 = $this->exercice;

        $this->service->genererNumero('FF', 'factures', $exercice2025);
        $this->service->genererNumero('FF', 'factures', $exercice2025);

        $numero2026 = $this->service->genererNumero('FF', 'factures', $exercice2026);

        $this->assertEquals('FF2026-001', $numero2026);
    }

    public function test_supprimer_la_derniere_facture_libere_le_numero(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-01-10'], $admin->id);
        $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-02-10'], $admin->id);
        $facture3 = $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-03-10'], $admin->id);

        $this->assertEquals('FF2026-003', $facture3->numero);

        // Hard delete de la derniere : la ligne disparait physiquement
        $this->service->supprimerFacture($facture3);
        $this->assertDatabaseMissing('factures', ['id' => $facture3->id]);

        // Le numero libere est reutilise, sans trou
        $this->assertEquals('FF2026-003', $this->service->genererNumero('FF', 'factures', $this->exercice));
    }

    public function test_supprimer_une_facture_non_derniere_est_refuse(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $facture1 = $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-01-10'], $admin->id);
        $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-02-10'], $admin->id);
        $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-03-10'], $admin->id);

        $this->expectException(DomainException::class);
        $this->service->supprimerFacture($facture1);
    }

    // -------------------------------------------------------------------------
    // Tranches de facturation 30% / 30% / 40%
    // -------------------------------------------------------------------------

    public function test_tranche_1_est_30_pourcent(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $facture = $this->service->creerFacture([
            'mission_id' => $this->mission->id,
            'date_facture' => '2026-01-15',
        ], $admin->id);

        $this->assertEquals(round(315000 * 0.30, 2), (float) $facture->montant_ht);
    }

    public function test_tranche_2_est_30_pourcent(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-01-15'], $admin->id);
        $facture2 = $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-02-15'], $admin->id);

        $this->assertEquals(round(315000 * 0.30, 2), (float) $facture2->montant_ht);
    }

    public function test_tranche_3_est_40_pourcent(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-01-15'], $admin->id);
        $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-02-15'], $admin->id);
        $facture3 = $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-03-15'], $admin->id);

        $this->assertEquals(round(315000 * 0.40, 2), (float) $facture3->montant_ht);
    }

    public function test_4eme_tranche_impossible(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-01-15'], $admin->id);
        $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-02-15'], $admin->id);
        $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-03-15'], $admin->id);

        $this->expectException(DomainException::class);
        $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-04-15'], $admin->id);
    }

    public function test_somme_des_trois_tranches_egale_le_prix_ht_avec_centimes(): void
    {
        // Prix avec centimes : la 3e tranche (solde exact) doit absorber l'arrondi
        // pour que T1 + T2 + T3 == prix_ht (anti perte d'arrondi).
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->mission->update(['prix_ht' => 100.01]);

        $f1 = $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-01-15'], $admin->id);
        $f2 = $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-02-15'], $admin->id);
        $f3 = $this->service->creerFacture(['mission_id' => $this->mission->id, 'date_facture' => '2026-03-15'], $admin->id);

        $somme = (float) $f1->montant_ht + (float) $f2->montant_ht + (float) $f3->montant_ht;

        $this->assertEquals(100.01, round($somme, 2));
        $this->assertEquals(40.01, (float) $f3->montant_ht); // 100.01 - 30.00 - 30.00
    }

    // -------------------------------------------------------------------------
    // TVA historisée — snapshot immuable à la date de facturation
    // -------------------------------------------------------------------------

    public function test_tva_snapshot_utilise_taux_en_vigueur_a_la_date_facture(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $facture = $this->service->creerFacture([
            'mission_id' => $this->mission->id,
            'date_facture' => '2026-03-01',
        ], $admin->id);

        $this->assertEquals(19, (float) $facture->taux_tva);
        $montantTvaAttendu = round(315000 * 0.30 * 0.19, 2);
        $this->assertEquals($montantTvaAttendu, (float) $facture->montant_tva);
    }

    public function test_tva_snapshot_2026_retourne_19_pourcent_meme_appele_apres(): void
    {
        // Ajouter un taux futur ne doit pas affecter les factures passées
        TvaTaux::create([
            'taux' => 22,
            'designation' => 'TVA future',
            'date_debut' => '2030-01-01',
            'date_fin' => null,
            'type' => 'standard',
            'actif' => true,
        ]);

        $taux = TvaTaux::enVigueurLe('2026-06-15');
        $this->assertEquals(19, (float) $taux->taux);
    }

    // -------------------------------------------------------------------------
    // Statut paiement automatique
    // -------------------------------------------------------------------------

    public function test_statut_passe_a_partiel_apres_paiement_partiel(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $facture = $this->service->creerFacture([
            'mission_id' => $this->mission->id,
            'date_facture' => '2026-01-15',
        ], $admin->id);

        Paiement::create([
            'facture_id' => $facture->id,
            'recorded_by' => $admin->id,
            'montant' => 1000,
            'date_paiement' => '2026-02-01',
            'mode_paiement' => 'virement',
        ]);

        $this->service->recalculerStatutPaiement($facture->fresh());

        $this->assertEquals('partiel', $facture->fresh()->statut_paiement);
    }

    public function test_statut_passe_a_solde_apres_paiement_complet(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $facture = $this->service->creerFacture([
            'mission_id' => $this->mission->id,
            'date_facture' => '2026-01-15',
        ], $admin->id);

        $facture->refresh();

        Paiement::create([
            'facture_id' => $facture->id,
            'recorded_by' => $admin->id,
            'montant' => $facture->montant_ttc,
            'date_paiement' => '2026-02-01',
            'mode_paiement' => 'virement',
        ]);

        $this->service->recalculerStatutPaiement($facture->fresh());

        $this->assertEquals('solde', $facture->fresh()->statut_paiement);
    }

    public function test_statut_reste_en_attente_sans_paiement(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $facture = $this->service->creerFacture([
            'mission_id' => $this->mission->id,
            'date_facture' => '2026-01-15',
        ], $admin->id);

        $this->assertEquals('en_attente', $facture->statut_paiement);
    }

    // -------------------------------------------------------------------------
    // Snapshots immuables
    // -------------------------------------------------------------------------

    public function test_snapshots_tva_figes_a_la_creation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $facture = $this->service->creerFacture([
            'mission_id' => $this->mission->id,
            'date_facture' => '2026-01-15',
        ], $admin->id);

        $tauxSnapshot = $facture->taux_tva;
        $montantTvaSnapshot = $facture->montant_tva;
        $montantTtcSnapshot = $facture->montant_ttc;

        // Modifier le taux TVA ne doit pas changer les snapshots existants
        TvaTaux::where('type', 'standard')->update(['taux' => 25]);

        $facture->refresh();

        $this->assertEquals($tauxSnapshot, $facture->taux_tva);
        $this->assertEquals($montantTvaSnapshot, $facture->montant_tva);
        $this->assertEquals($montantTtcSnapshot, $facture->montant_ttc);
    }
}
