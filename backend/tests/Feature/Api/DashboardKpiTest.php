<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\Setting;
use App\Models\TimbreTaux;
use App\Models\TvaTaux;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardKpiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'client']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->exercice = Exercice::firstOrCreate(
            ['annee' => 2026],
            ['date_ouverture' => '2026-01-01', 'statut' => 'ouvert']
        );

        Setting::updateOrInsert(
            ['key' => 'seuil_alerte_recouvrement'],
            ['value' => '70', 'group' => 'dashboard', 'label' => 'Seuil alerte', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function creerFacture(array $overrides = []): Facture
    {
        $tva = TvaTaux::firstOrCreate(
            ['taux' => 19],
            ['designation' => 'TVA 19%', 'date_debut' => '2020-01-01', 'actif' => true]
        );
        $timbre = TimbreTaux::firstOrCreate(
            ['taux' => 1],
            ['designation' => 'Timbre 1%', 'date_debut' => '2020-01-01', 'plafond' => 2500, 'actif' => true]
        );
        $entreprise = Entreprise::factory()->create(['statut' => 'client']);
        $prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            ['designation' => 'Comptabilité', 'tarif_initial' => 120000]
        );
        $mission = Mission::factory()->create([
            'entreprise_id' => $entreprise->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $prestation->id,
            'prix_ht' => 100000,
        ]);

        return Facture::factory()->create(array_merge([
            'type' => 'FF',
            'entreprise_id' => $entreprise->id,
            'exercice_id' => $this->exercice->id,
            'mission_id' => $mission->id,
            'tva_taux_id' => $tva->id,
            'timbre_taux_id' => $timbre->id,
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

    public function test_stats_retourne_structure_kpi(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'kpi' => ['ca_mois', 'tva_collectee', 'taux_recouvrement', 'seuil_recouvrement'],
                    'alertes',
                    'exercices',
                    'entreprises',
                    'missions',
                    'factures',
                    'devis',
                    'recentes',
                ],
            ]);
    }

    public function test_kpi_ca_mois_et_tva_collectee(): void
    {
        $this->creerFacture(['montant_ttc' => 119000, 'montant_tva' => 19000]);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $this->assertEquals(119000.0, $res->json('data.kpi.ca_mois'));
        $this->assertEquals(19000.0, $res->json('data.kpi.tva_collectee'));
    }

    public function test_kpi_taux_recouvrement_calcule(): void
    {
        $this->creerFacture(['montant_ttc' => 100000, 'montant_paye' => 60000, 'statut_paiement' => 'partiel']);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $this->assertEquals(60.0, $res->json('data.kpi.taux_recouvrement'));
    }

    public function test_filtre_par_exercice(): void
    {
        $autreExercice = Exercice::firstOrCreate(
            ['annee' => 2025],
            ['date_ouverture' => '2025-01-01', 'statut' => 'cloture']
        );

        // Facture dans exercice 2025
        $this->creerFacture(['exercice_id' => $autreExercice->id, 'montant_ttc' => 50000]);
        // Facture dans exercice 2026
        $this->creerFacture(['exercice_id' => $this->exercice->id, 'montant_ttc' => 80000]);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats?exercice_id='.$this->exercice->id)
            ->assertOk();

        $this->assertEquals(80000.0, $res->json('data.factures.ca_ttc'));
    }

    public function test_alerte_factures_en_retard(): void
    {
        $this->creerFacture([
            'date_echeance' => now()->subDays(5)->toDateString(),
            'statut_paiement' => 'en_attente',
        ]);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $alertes = $res->json('data.alertes');
        $this->assertNotEmpty($alertes);
        $dangerAlerte = collect($alertes)->firstWhere('type', 'danger');
        $this->assertNotNull($dangerAlerte);
        $this->assertStringContainsString('retard', $dangerAlerte['message']);
    }

    public function test_alerte_taux_recouvrement_faible(): void
    {
        // Taux recouvrement = 50% < seuil 70%
        $this->creerFacture(['montant_ttc' => 100000, 'montant_paye' => 50000, 'statut_paiement' => 'partiel']);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $alertes = $res->json('data.alertes');
        $warnAlerte = collect($alertes)->firstWhere('type', 'warn');
        $this->assertNotNull($warnAlerte);
        $this->assertStringContainsString('recouvrement', $warnAlerte['message']);
    }

    public function test_pas_alerte_si_tout_solde(): void
    {
        $this->creerFacture(['montant_ttc' => 100000, 'montant_paye' => 100000, 'statut_paiement' => 'solde']);

        $res = $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $this->assertEmpty($res->json('data.alertes'));
    }

    public function test_client_ne_peut_pas_acceder_au_dashboard(): void
    {
        $client = User::factory()->create(['entreprise_id' => Entreprise::factory()->create()->id]);
        $client->assignRole('client');

        $this->actingAs($client)
            ->getJson('/api/v1/stats')
            ->assertForbidden();
    }

    public function test_non_authentifie_recu_401(): void
    {
        $this->getJson('/api/v1/stats')->assertUnauthorized();
    }
}
