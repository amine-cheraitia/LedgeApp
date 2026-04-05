<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\KpiObjectif;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KpiObjectifsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $collaborateur;

    private Exercice $exercice;

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

        $this->exercice = Exercice::factory()->create(['statut' => 'ouvert', 'annee' => 2026]);
    }

    public function test_admin_peut_lister_collaborateurs_avec_kpi(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/kpi/objectifs?exercice_id='.$this->exercice->id);

        $response->assertOk()
            ->assertJsonStructure(['data' => [['user', 'objectifs', 'realise']]]);
    }

    public function test_lister_sans_exercice(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/kpi/objectifs');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_upsert_objectif_ca_ht(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/kpi/objectifs', [
                'user_id' => $this->collaborateur->id,
                'exercice_id' => $this->exercice->id,
                'type' => 'ca_ht',
                'valeur' => 500000,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('kpi_objectifs', [
            'user_id' => $this->collaborateur->id,
            'exercice_id' => $this->exercice->id,
            'type' => 'ca_ht',
            'valeur' => 500000,
        ]);
    }

    public function test_upsert_met_a_jour_objectif_existant(): void
    {
        KpiObjectif::create([
            'user_id' => $this->collaborateur->id,
            'exercice_id' => $this->exercice->id,
            'type' => 'ca_ht',
            'valeur' => 300000,
        ]);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/kpi/objectifs', [
                'user_id' => $this->collaborateur->id,
                'exercice_id' => $this->exercice->id,
                'type' => 'ca_ht',
                'valeur' => 600000,
            ]);

        $this->assertDatabaseCount('kpi_objectifs', 1);
        $this->assertDatabaseHas('kpi_objectifs', ['valeur' => 600000]);
    }

    public function test_supprimer_objectif(): void
    {
        $objectif = KpiObjectif::create([
            'user_id' => $this->collaborateur->id,
            'exercice_id' => $this->exercice->id,
            'type' => 'missions_cloturees',
            'valeur' => 10,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/kpi/objectifs/{$objectif->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('kpi_objectifs', ['id' => $objectif->id]);
    }

    public function test_realise_ca_ht_calcule_depuis_missions_terminees(): void
    {
        $entreprise = Entreprise::factory()->create(['statut' => 'client', 'regime_fiscal' => 'reel', 'categorie' => 'PME']);
        $prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            ['designation' => 'Accompagnement comptable', 'tarif_initial' => 120000, 'duree_mois' => 12]
        );

        $mission = Mission::factory()->create([
            'entreprise_id' => $entreprise->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $prestation->id,
            'statut' => 'terminee',
            'prix_ht' => 315000,
        ]);

        $mission->collaborateurs()->attach($this->collaborateur->id);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/kpi/objectifs?exercice_id='.$this->exercice->id);

        $response->assertOk();

        $collabData = collect($response->json('data'))
            ->firstWhere('user.id', $this->collaborateur->id);

        $this->assertEquals(315000.0, $collabData['realise']['ca_ht']);
        $this->assertEquals(1.0, $collabData['realise']['missions_cloturees']);
    }

    public function test_validation_type_invalide(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/kpi/objectifs', [
                'user_id' => $this->collaborateur->id,
                'exercice_id' => $this->exercice->id,
                'type' => 'type_inconnu',
                'valeur' => 100,
            ]);

        $response->assertStatus(422);
    }

    public function test_client_ne_peut_pas_acceder(): void
    {
        $entreprise = Entreprise::factory()->create(['statut' => 'client']);
        $client = User::factory()->create(['entreprise_id' => $entreprise->id, 'portail_actif' => true]);
        $client->assignRole('client');

        $response = $this->actingAs($client)
            ->getJson('/api/v1/kpi/objectifs');

        $response->assertForbidden();
    }
}
