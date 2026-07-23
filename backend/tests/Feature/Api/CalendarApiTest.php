<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CategorieEntreprise;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\RegimeFiscal;
use App\Models\Tache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalendarApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $collaborateur;

    private Entreprise $entreprise;

    private Prestation $prestation;

    private Mission $mission;

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

        RegimeFiscal::create(['code' => 'reel', 'designation' => 'Reel', 'indice' => 1.5]);
        CategorieEntreprise::create(['code' => 'PME', 'designation' => 'PME', 'indice' => 1.75]);

        $this->entreprise = Entreprise::factory()->create([
            'regime_fiscal' => 'reel',
            'categorie' => 'PME',
            'statut' => 'client',
        ]);

        $this->prestation = Prestation::create([
            'code' => 'ACMPT',
            'designation' => 'Assistance Comptable',
            'tarif_initial' => 120000,
            'duree_mois' => 12,
            'actif' => true,
        ]);

        $exercice = Exercice::create([
            'annee' => (int) date('Y'),
            'date_ouverture' => date('Y').'-01-01',
            'date_cloture' => date('Y').'-12-31',
            'statut' => 'ouvert',
        ]);

        $this->mission = Mission::create([
            'entreprise_id' => $this->entreprise->id,
            'exercice_id' => $exercice->id,
            'prestation_id' => $this->prestation->id,
            'reference' => 'M'.date('Y').'-001',
            'prix_ht' => 315000,
            'date_debut' => '2026-04-01',
            'date_fin' => '2027-03-31',
            'statut' => 'en_cours',
        ]);
    }

    public function test_mission_dans_le_range_apparait(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/calendar?from=2026-03-01&to=2026-05-31');

        $response->assertOk();
        $missions = $response->json('data.missions');
        $this->assertNotEmpty($missions);
        $this->assertEquals($this->mission->id, $missions[0]['id']);
        $this->assertEquals('mission', $missions[0]['type']);
    }

    public function test_mission_hors_range_napparait_pas(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/calendar?from=2025-01-01&to=2025-12-31');

        $response->assertOk();
        $missions = $response->json('data.missions');
        $this->assertEmpty($missions);
    }

    public function test_mission_qui_englobe_le_range_apparait(): void
    {
        // La mission couvre 2026-04-01 → 2027-03-31 → elle englobe la range 2026-06-01/2026-08-31
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/calendar?from=2026-06-01&to=2026-08-31');

        $response->assertOk();
        $missions = $response->json('data.missions');
        $this->assertNotEmpty($missions);
        $this->assertEquals($this->mission->id, $missions[0]['id']);
    }

    public function test_tache_avec_echeance_dans_le_range_apparait(): void
    {
        Tache::create([
            'mission_id' => $this->mission->id,
            'titre' => 'Audit Q1',
            'statut' => 'a_faire',
            'date_echeance' => '2026-04-15',
            'priorite' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/calendar?from=2026-04-01&to=2026-04-30');

        $response->assertOk();
        $taches = $response->json('data.taches');
        $this->assertNotEmpty($taches);
        $this->assertEquals('tache', $taches[0]['type']);
        $this->assertEquals('Audit Q1', $taches[0]['titre']);
    }

    public function test_tache_hors_range_napparait_pas(): void
    {
        Tache::create([
            'mission_id' => $this->mission->id,
            'titre' => 'Audit Q2',
            'statut' => 'a_faire',
            'date_echeance' => '2026-07-01',
            'priorite' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/calendar?from=2026-04-01&to=2026-04-30');

        $response->assertOk();
        $taches = $response->json('data.taches');
        $this->assertEmpty($taches);
    }

    public function test_tache_dont_la_plage_chevauche_le_range_apparait(): void
    {
        // Debut avant la fenetre, echeance apres -> la plage chevauche entierement le range.
        // L'ancien filtre (echeance entre from/to) la ratait : ce test valide le filtre par chevauchement
        // et la presence de date_debut dans le payload.
        Tache::create([
            'mission_id' => $this->mission->id,
            'titre' => 'Cadrage long',
            'statut' => 'en_cours',
            'date_debut' => '2026-03-20',
            'date_echeance' => '2026-05-10',
            'priorite' => 2,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/calendar?from=2026-04-01&to=2026-04-30');

        $response->assertOk();
        $taches = $response->json('data.taches');
        $this->assertNotEmpty($taches);
        $this->assertEquals('Cadrage long', $taches[0]['titre']);
        $this->assertEquals('2026-03-20', $taches[0]['date_debut']);
        $this->assertEquals('2026-05-10', $taches[0]['date_echeance']);
    }

    public function test_filtre_collaborateur_id_filtre_les_missions(): void
    {
        // Affecter le collaborateur à la mission
        $this->mission->collaborateurs()->attach($this->collaborateur->id, ['role_mission' => 'executant']);

        // Mission sans collaborateur affecté
        $autreMission = Mission::create([
            'entreprise_id' => $this->entreprise->id,
            'exercice_id' => $this->mission->exercice_id,
            'prestation_id' => $this->prestation->id,
            'reference' => 'M'.date('Y').'-002',
            'prix_ht' => 315000,
            'date_debut' => '2026-04-01',
            'date_fin' => '2026-12-31',
            'statut' => 'en_cours',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/calendar?from=2026-04-01&to=2026-05-31&collaborateur_id='.$this->collaborateur->id);

        $response->assertOk();
        $missions = $response->json('data.missions');
        $this->assertCount(1, $missions);
        $this->assertEquals($this->mission->id, $missions[0]['id']);
    }

    public function test_retourne_401_si_non_authentifie(): void
    {
        $this->getJson('/api/v1/calendar?from=2026-04-01&to=2026-04-30')
            ->assertUnauthorized();
    }

    public function test_retourne_403_si_client(): void
    {
        $client = User::factory()->create(['entreprise_id' => $this->entreprise->id, 'portail_actif' => true]);
        $client->assignRole('client');

        $this->actingAs($client)
            ->getJson('/api/v1/calendar?from=2026-04-01&to=2026-04-30')
            ->assertForbidden();
    }
}
