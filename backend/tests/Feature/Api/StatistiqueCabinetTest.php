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

/**
 * GET /api/v1/stats/cabinet — StatistiqueService::getCabinetStats (admin uniquement).
 */
class StatistiqueCabinetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $secretaire;

    private User $collaborateur;

    private Exercice $exercice;

    private Prestation $prestation;

    private TvaTaux $tva;

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

        $this->exercice = Exercice::firstOrCreate(
            ['annee' => (int) now()->year],
            ['date_ouverture' => now()->year.'-01-01', 'statut' => 'ouvert']
        );

        $this->prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            ['designation' => 'Comptabilité', 'tarif_initial' => 120000]
        );

        $this->tva = TvaTaux::firstOrCreate(
            ['taux' => 19],
            ['designation' => 'TVA 19%', 'date_debut' => '2020-01-01', 'actif' => true]
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function creerFacture(Entreprise $entreprise, array $overrides = []): Facture
    {
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

    private function creerAvoir(Facture $facture, float $montantHt, float $montantTtc): Avoir
    {
        static $sequence = 0;
        $sequence++;

        return Avoir::create([
            'facture_origine_id' => $facture->id,
            'exercice_id' => $this->exercice->id,
            'created_by' => $this->admin->id,
            'numero' => 'FA'.now()->year.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            'date_avoir' => now()->toDateString(),
            'montant_ht' => $montantHt,
            'montant_ttc' => $montantTtc,
            'motif' => 'Test',
        ]);
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

    // -------------------------------------------------------------------------
    // top_entreprises
    // -------------------------------------------------------------------------

    public function test_top_entreprises_ca_ht_net_avoirs(): void
    {
        $entreprise = Entreprise::factory()->create(['statut' => 'client']);
        $facture = $this->creerFacture($entreprise, ['montant_ht' => 100000]);
        $this->creerAvoir($facture, 10000, 11900);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats/cabinet')
            ->assertOk();

        $top = collect($res->json('data.top_entreprises'))->firstWhere('entreprise_id', $entreprise->id);
        $this->assertNotNull($top);
        $this->assertEquals(90000.0, $top['ca_ht_net']);
        $this->assertSame($entreprise->raison_sociale, $top['raison_sociale']);
    }

    public function test_top_entreprises_exclut_les_totaux_negatifs_ou_nuls(): void
    {
        $entreprise = Entreprise::factory()->create(['statut' => 'client']);
        $facture = $this->creerFacture($entreprise, ['montant_ht' => 50000]);
        // Avoir integral -> ca_ht_net = 0 -> exclu
        $this->creerAvoir($facture, 50000, 59500);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats/cabinet')
            ->assertOk();

        $top = collect($res->json('data.top_entreprises'))->firstWhere('entreprise_id', $entreprise->id);
        $this->assertNull($top);
    }

    public function test_top_entreprises_tri_desc_et_limite_a_huit(): void
    {
        $entreprises = [];
        for ($i = 0; $i < 9; $i++) {
            $entreprise = Entreprise::factory()->create(['statut' => 'client']);
            $this->creerFacture($entreprise, ['montant_ht' => ($i + 1) * 10000]);
            $entreprises[] = $entreprise;
        }

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats/cabinet')
            ->assertOk();

        $top = $res->json('data.top_entreprises');
        $this->assertCount(8, $top);

        // La plus grosse entreprise (90000 HT, index 8) doit etre en tete
        $this->assertSame($entreprises[8]->id, $top[0]['entreprise_id']);
        $this->assertEquals(90000.0, $top[0]['ca_ht_net']);

        // Tri strictement decroissant
        $montants = collect($top)->pluck('ca_ht_net')->all();
        $tries = $montants;
        rsort($tries);
        $this->assertSame($tries, $montants);
    }

    public function test_top_entreprises_scope_par_exercice(): void
    {
        $exercicePrecedent = Exercice::firstOrCreate(
            ['annee' => (int) now()->year - 1],
            ['date_ouverture' => (now()->year - 1).'-01-01', 'statut' => 'cloture']
        );

        $entreprise = Entreprise::factory()->create(['statut' => 'client']);
        $this->creerFacture($entreprise, ['montant_ht' => 70000, 'exercice_id' => $exercicePrecedent->id]);
        $this->creerFacture($entreprise, ['montant_ht' => 30000]);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats/cabinet?exercice_id='.$this->exercice->id)
            ->assertOk();

        $top = collect($res->json('data.top_entreprises'))->firstWhere('entreprise_id', $entreprise->id);
        $this->assertEquals(30000.0, $top['ca_ht_net']);
    }

    // -------------------------------------------------------------------------
    // missions_par_prestation / missions_par_etat
    // -------------------------------------------------------------------------

    public function test_missions_par_prestation(): void
    {
        $autrePrestation = Prestation::firstOrCreate(
            ['code' => 'AJUR'],
            ['designation' => 'Assistance juridique', 'tarif_initial' => 80000]
        );

        $this->creerMission(['prestation_id' => $this->prestation->id]);
        $this->creerMission(['prestation_id' => $this->prestation->id]);
        $this->creerMission(['prestation_id' => $autrePrestation->id]);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats/cabinet')
            ->assertOk();

        $parPrestation = collect($res->json('data.missions_par_prestation'));

        $ligneComptabilite = $parPrestation->firstWhere('prestation_id', $this->prestation->id);
        $this->assertSame(2, $ligneComptabilite['total']);
        $this->assertSame('Comptabilité', $ligneComptabilite['designation']);

        $ligneJuridique = $parPrestation->firstWhere('prestation_id', $autrePrestation->id);
        $this->assertSame(1, $ligneJuridique['total']);
        $this->assertSame('Assistance juridique', $ligneJuridique['designation']);
    }

    public function test_missions_par_etat(): void
    {
        $this->creerMission(['statut' => 'en_cours']);
        $this->creerMission(['statut' => 'en_cours']);
        $this->creerMission(['statut' => 'terminee']);
        $this->creerMission(['statut' => 'suspendue']);
        $this->creerMission(['statut' => 'annulee']);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats/cabinet')
            ->assertOk();

        $this->assertSame(2, $res->json('data.missions_par_etat.en_cours'));
        $this->assertSame(1, $res->json('data.missions_par_etat.terminee'));
        $this->assertSame(1, $res->json('data.missions_par_etat.suspendue'));
        $this->assertSame(1, $res->json('data.missions_par_etat.annulee'));
    }

    // -------------------------------------------------------------------------
    // creances (aging + total_impaye avoirs deduits)
    // -------------------------------------------------------------------------

    public function test_creances_total_impaye_deduit_avoirs(): void
    {
        $entreprise = Entreprise::factory()->create(['statut' => 'client']);
        $facture = $this->creerFacture($entreprise, ['montant_ttc' => 100000, 'montant_paye' => 0]);
        $this->creerAvoir($facture, 10000, 11900);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats/cabinet')
            ->assertOk();

        $this->assertEquals(88100.0, $res->json('data.creances.total_impaye'));
    }

    public function test_creances_aging_par_tranches(): void
    {
        $e1 = Entreprise::factory()->create(['statut' => 'client']);
        $e2 = Entreprise::factory()->create(['statut' => 'client']);
        $e3 = Entreprise::factory()->create(['statut' => 'client']);

        $this->creerFacture($e1, ['montant_ttc' => 50000, 'date_echeance' => now()->subDays(20)->toDateString()]);
        $this->creerFacture($e2, ['montant_ttc' => 70000, 'date_echeance' => now()->subDays(45)->toDateString()]);
        $this->creerFacture($e3, ['montant_ttc' => 90000, 'date_echeance' => now()->subDays(90)->toDateString()]);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats/cabinet')
            ->assertOk();

        $this->assertEquals(50000.0, $res->json('data.creances.aging.retard_15_30'));
        $this->assertEquals(70000.0, $res->json('data.creances.aging.retard_30_60'));
        $this->assertEquals(90000.0, $res->json('data.creances.aging.retard_60_plus'));
    }

    public function test_creances_top_debiteurs(): void
    {
        $gros = Entreprise::factory()->create(['statut' => 'client', 'raison_sociale' => 'Gros Debiteur SARL']);
        $petit = Entreprise::factory()->create(['statut' => 'client', 'raison_sociale' => 'Petit Debiteur SARL']);

        $this->creerFacture($gros, ['montant_ttc' => 100000]);
        $this->creerFacture($petit, ['montant_ttc' => 30000]);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats/cabinet')
            ->assertOk();

        $top = $res->json('data.creances.top_debiteurs');
        $this->assertSame($gros->id, $top[0]['entreprise_id']);
        $this->assertEquals(100000.0, $top[0]['montant_impaye']);
    }

    public function test_creances_scope_par_exercice(): void
    {
        $exercicePrecedent = Exercice::firstOrCreate(
            ['annee' => (int) now()->year - 1],
            ['date_ouverture' => (now()->year - 1).'-01-01', 'statut' => 'cloture']
        );

        $entreprise = Entreprise::factory()->create(['statut' => 'client']);
        $this->creerFacture($entreprise, ['montant_ttc' => 40000, 'exercice_id' => $exercicePrecedent->id]);
        $this->creerFacture($entreprise, ['montant_ttc' => 60000]);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats/cabinet?exercice_id='.$this->exercice->id)
            ->assertOk();

        $this->assertEquals(60000.0, $res->json('data.creances.total_impaye'));
    }

    // -------------------------------------------------------------------------
    // Roles
    // -------------------------------------------------------------------------

    public function test_admin_accede_aux_stats_cabinet(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/stats/cabinet')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'top_entreprises',
                    'missions_par_prestation',
                    'missions_par_etat' => ['en_cours', 'terminee', 'suspendue', 'annulee'],
                    'creances' => ['total_impaye', 'aging' => ['retard_15_30', 'retard_30_60', 'retard_60_plus'], 'top_debiteurs'],
                ],
            ]);
    }

    public function test_secretaire_ne_peut_pas_acceder_aux_stats_cabinet(): void
    {
        $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats/cabinet')
            ->assertForbidden();
    }

    public function test_collaborateur_ne_peut_pas_acceder_aux_stats_cabinet(): void
    {
        $this->actingAs($this->collaborateur)
            ->getJson('/api/v1/stats/cabinet')
            ->assertForbidden();
    }

    public function test_client_ne_peut_pas_acceder_aux_stats_cabinet(): void
    {
        $client = User::factory()->create(['entreprise_id' => Entreprise::factory()->create()->id]);
        $client->assignRole('client');

        $this->actingAs($client)
            ->getJson('/api/v1/stats/cabinet')
            ->assertForbidden();
    }

    public function test_non_authentifie_recoit_401(): void
    {
        $this->getJson('/api/v1/stats/cabinet')->assertUnauthorized();
    }
}
