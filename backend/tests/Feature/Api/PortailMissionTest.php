<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\Tache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortailMissionTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Entreprise $entreprise;

    private Exercice $exercice;

    private Prestation $prestation;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);

        $this->entreprise = Entreprise::factory()->create(['statut' => 'client']);
        $this->exercice = Exercice::factory()->create(['statut' => 'ouvert', 'annee' => 2026]);
        $this->prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            ['designation' => 'Accompagnement comptable', 'tarif_initial' => 120000, 'duree_mois' => 12]
        );

        $this->client = User::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'portail_actif' => true,
        ]);
        $this->client->assignRole('client');
    }

    private function creerMission(array $overrides = []): Mission
    {
        return Mission::factory()->create(array_merge([
            'entreprise_id' => $this->entreprise->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
        ], $overrides));
    }

    public function test_client_voit_ses_missions(): void
    {
        $this->creerMission();
        $this->creerMission();

        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/portail/missions');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_client_ne_voit_pas_missions_autre_entreprise(): void
    {
        $autreEntreprise = Entreprise::factory()->create(['statut' => 'client']);
        Mission::factory()->create([
            'entreprise_id' => $autreEntreprise->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
        ]);

        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/portail/missions');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_filtre_par_statut(): void
    {
        $this->creerMission(['statut' => 'en_cours']);
        $this->creerMission(['statut' => 'terminee']);

        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/portail/missions?statut=en_cours');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_detail_mission_avec_taches(): void
    {
        $mission = $this->creerMission();
        Tache::factory()->count(3)->create(['mission_id' => $mission->id]);

        $response = $this->actingAs($this->client)
            ->getJson("/api/v1/portail/missions/{$mission->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $mission->id)
            ->assertJsonCount(3, 'data.taches');
    }

    public function test_detail_mission_autre_entreprise_interdit(): void
    {
        $autreEntreprise = Entreprise::factory()->create(['statut' => 'client']);
        $mission = Mission::factory()->create([
            'entreprise_id' => $autreEntreprise->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
        ]);

        $response = $this->actingAs($this->client)
            ->getJson("/api/v1/portail/missions/{$mission->id}");

        $response->assertForbidden();
    }

    public function test_taches_sans_commentaires_internes(): void
    {
        $mission = $this->creerMission();
        Tache::factory()->create(['mission_id' => $mission->id]);

        $response = $this->actingAs($this->client)
            ->getJson("/api/v1/portail/missions/{$mission->id}");

        $response->assertOk();
        $taches = $response->json('data.taches');
        $this->assertNotEmpty($taches);
        $this->assertArrayNotHasKey('commentaires', $taches[0]);
    }

    public function test_staff_ne_peut_pas_acceder_portail_missions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/portail/missions');

        $response->assertForbidden();
    }
}
