<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Avoir;
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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardSecretaireTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $secretaire;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'secretaire']);
        Role::firstOrCreate(['name' => 'client']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->secretaire = User::factory()->create();
        $this->secretaire->assignRole('secretaire');

        $this->exercice = Exercice::firstOrCreate(
            ['annee' => 2026],
            ['date_ouverture' => '2026-01-01', 'statut' => 'ouvert']
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

    private function creerFacture(array $overrides = []): Facture
    {
        $tva = TvaTaux::firstOrCreate(
            ['taux' => 19],
            ['designation' => 'TVA 19%', 'date_debut' => '2020-01-01', 'actif' => true]
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

    public function test_secretaire_stats_retourne_structure(): void
    {
        $this->actingAs($this->secretaire)
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
            ])
            // Le volet facturation/production a ete retire du dashboard secretaire
            ->assertJsonMissingPath('data.facturation')
            ->assertJsonMissingPath('data.factures_emises');
    }

    public function test_paiement_du_mois_compte_dans_encaissements(): void
    {
        $facture = $this->creerFacture();

        Paiement::create([
            'facture_id' => $facture->id,
            'recorded_by' => $this->admin->id,
            'montant' => 50000,
            'date_paiement' => now()->toDateString(),
            'mode_paiement' => 'virement',
        ]);

        $res = $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats/secretaire')
            ->assertOk();

        $this->assertSame(1, $res->json('data.encaissements_mois.count'));
        $this->assertEquals(50000.0, $res->json('data.encaissements_mois.montant'));
    }

    public function test_action_factures_en_retard_listee(): void
    {
        $this->creerFacture([
            'montant_ttc' => 80000,
            'montant_paye' => 0,
            'statut_paiement' => 'en_attente',
            'date_echeance' => now()->subDays(20)->toDateString(),
        ]);

        $res = $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats/secretaire')
            ->assertOk();

        $keys = collect($res->json('data.actions'))->pluck('key');
        $this->assertContains('factures_retard', $keys);
    }

    public function test_impaye_deduit_avoirs(): void
    {
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
            'numero' => 'FA2026-001',
            'type' => 'FA',
            'date_avoir' => now()->toDateString(),
            'montant_ht' => 10000,
            'montant_ttc' => 11900,
            'motif' => 'Test',
        ]);

        $res = $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats/secretaire')
            ->assertOk();

        $this->assertEquals(88100.0, $res->json('data.creances.total_impaye'));
    }

    public function test_aging_bucket_retard_15_30(): void
    {
        $this->creerFacture([
            'montant_ttc' => 50000,
            'montant_paye' => 0,
            'statut_paiement' => 'en_attente',
            'date_echeance' => now()->subDays(20)->toDateString(),
        ]);

        $res = $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats/secretaire')
            ->assertOk();

        $this->assertEquals(50000.0, $res->json('data.aging.retard_15_30'));
        $this->assertEquals(0.0, $res->json('data.aging.retard_30_60'));
    }

    public function test_secretaire_accede_stats_secretaire_mais_pas_admin(): void
    {
        $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats/secretaire')
            ->assertOk();

        $this->actingAs($this->secretaire)
            ->getJson('/api/v1/stats')
            ->assertForbidden();
    }

    public function test_admin_accede_stats_admin_mais_pas_secretaire(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/stats')
            ->assertOk();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/stats/secretaire')
            ->assertForbidden();
    }

    public function test_client_ne_peut_pas_acceder_aux_deux_stats(): void
    {
        $client = User::factory()->create(['entreprise_id' => Entreprise::factory()->create()->id]);
        $client->assignRole('client');

        $this->actingAs($client)
            ->getJson('/api/v1/stats/secretaire')
            ->assertForbidden();

        $this->actingAs($client)
            ->getJson('/api/v1/stats')
            ->assertForbidden();
    }
}
