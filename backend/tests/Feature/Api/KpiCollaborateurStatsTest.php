<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\KpiObjectif;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\Tache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * GET /api/v1/kpi/collaborateurs/{user}/stats — KpiService::getCollaborateurStats.
 */
class KpiCollaborateurStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $collaborateur;

    private User $secretaire;

    private Exercice $exercice;

    private Prestation $prestation;

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

        $this->exercice = Exercice::firstOrCreate(
            ['annee' => (int) now()->year],
            ['date_ouverture' => now()->year.'-01-01', 'statut' => 'ouvert']
        );

        $this->prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            ['designation' => 'Comptabilité', 'tarif_initial' => 120000]
        );
    }

    private function creerMissionTerminee(User $collaborateur, string $dateFin, float $prixHt): Mission
    {
        $mission = Mission::factory()->create([
            'entreprise_id' => Entreprise::factory()->create(['statut' => 'client'])->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
            'statut' => 'terminee',
            'date_fin' => $dateFin,
            'prix_ht' => $prixHt,
        ]);
        $mission->collaborateurs()->attach($collaborateur->id);

        return $mission;
    }

    // -------------------------------------------------------------------------
    // realise_mensuel
    // -------------------------------------------------------------------------

    public function test_realise_mensuel_bucket_du_mois_de_date_fin(): void
    {
        // Mission terminee en mars (mois 3, index 2) -> 150000 HT
        $this->creerMissionTerminee($this->collaborateur, now()->year.'-03-15', 150000);

        $res = $this->actingAs($this->admin)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats?exercice_id=".$this->exercice->id)
            ->assertOk();

        $data = $res->json('data.realise_mensuel.data');
        $this->assertCount(12, $data);
        $this->assertEquals(150000.0, $data[2]);
        $this->assertEquals(0.0, $data[0]);
        $this->assertSame((int) now()->year, $res->json('data.realise_mensuel.annee'));
    }

    public function test_realise_mensuel_ignore_missions_non_terminees(): void
    {
        Mission::factory()->create([
            'entreprise_id' => Entreprise::factory()->create(['statut' => 'client'])->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
            'statut' => 'en_cours',
            'date_fin' => now()->year.'-06-10',
            'prix_ht' => 200000,
        ])->collaborateurs()->attach($this->collaborateur->id);

        $res = $this->actingAs($this->admin)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats?exercice_id=".$this->exercice->id)
            ->assertOk();

        $this->assertEquals(array_fill(0, 12, 0.0), $res->json('data.realise_mensuel.data'));
    }

    // -------------------------------------------------------------------------
    // taches_par_statut
    // -------------------------------------------------------------------------

    public function test_taches_par_statut_comptes(): void
    {
        $mission = Mission::factory()->create([
            'entreprise_id' => Entreprise::factory()->create(['statut' => 'client'])->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
        ]);

        Tache::factory()->create(['mission_id' => $mission->id, 'assigned_to' => $this->collaborateur->id, 'statut' => 'a_faire']);
        Tache::factory()->create(['mission_id' => $mission->id, 'assigned_to' => $this->collaborateur->id, 'statut' => 'en_cours']);
        Tache::factory()->count(2)->create(['mission_id' => $mission->id, 'assigned_to' => $this->collaborateur->id, 'statut' => 'terminee']);
        Tache::factory()->create(['mission_id' => $mission->id, 'assigned_to' => $this->collaborateur->id, 'statut' => 'bloquee']);

        $res = $this->actingAs($this->admin)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats?exercice_id=".$this->exercice->id)
            ->assertOk();

        $this->assertSame(1, $res->json('data.taches_par_statut.a_faire'));
        $this->assertSame(1, $res->json('data.taches_par_statut.en_cours'));
        $this->assertSame(2, $res->json('data.taches_par_statut.terminee'));
        $this->assertSame(1, $res->json('data.taches_par_statut.bloquee'));
    }

    public function test_taches_par_statut_scope_exercice(): void
    {
        $exercicePrecedent = Exercice::firstOrCreate(
            ['annee' => (int) now()->year - 1],
            ['date_ouverture' => (now()->year - 1).'-01-01', 'statut' => 'cloture']
        );

        $missionAutreExercice = Mission::factory()->create([
            'entreprise_id' => Entreprise::factory()->create(['statut' => 'client'])->id,
            'exercice_id' => $exercicePrecedent->id,
            'prestation_id' => $this->prestation->id,
        ]);
        Tache::factory()->create(['mission_id' => $missionAutreExercice->id, 'assigned_to' => $this->collaborateur->id, 'statut' => 'a_faire']);

        $res = $this->actingAs($this->admin)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats?exercice_id=".$this->exercice->id)
            ->assertOk();

        $this->assertSame(0, $res->json('data.taches_par_statut.a_faire'));
    }

    // -------------------------------------------------------------------------
    // objectifs
    // -------------------------------------------------------------------------

    public function test_objectifs_format_id_valeur(): void
    {
        $objectif = KpiObjectif::create([
            'user_id' => $this->collaborateur->id,
            'exercice_id' => $this->exercice->id,
            'type' => 'ca_ht',
            'valeur' => 400000,
        ]);

        $res = $this->actingAs($this->admin)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats?exercice_id=".$this->exercice->id)
            ->assertOk();

        $this->assertSame($objectif->id, $res->json('data.objectifs.ca_ht.id'));
        $this->assertEquals(400000.0, $res->json('data.objectifs.ca_ht.valeur'));
    }

    public function test_structure_reponse_complete(): void
    {
        $this->actingAs($this->admin)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'objectifs',
                    'realise' => ['ca_ht', 'missions_cloturees', 'taches_terminees', 'taches_en_retard', 'delai_moyen_tache'],
                    'realise_mensuel' => ['annee', 'data'],
                    'taches_par_statut' => ['a_faire', 'en_cours', 'terminee', 'bloquee'],
                    'missions_par_prestation',
                ],
            ]);
    }

    // -------------------------------------------------------------------------
    // missions_par_prestation
    // -------------------------------------------------------------------------

    public function test_missions_par_prestation_compte_la_participation(): void
    {
        $audit = Prestation::firstOrCreate(
            ['code' => 'CAC'],
            ['designation' => 'Audit legal', 'tarif_initial' => 300000]
        );

        // 2 missions Comptabilite (terminees) + 1 Audit (en cours) : la
        // participation compte tous les statuts, pas seulement les cloturees.
        $this->creerMissionTerminee($this->collaborateur, now()->year.'-02-10', 100000);
        $this->creerMissionTerminee($this->collaborateur, now()->year.'-04-10', 100000);

        $missionAudit = Mission::factory()->create([
            'entreprise_id' => Entreprise::factory()->create(['statut' => 'client'])->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $audit->id,
            'statut' => 'en_cours',
            'prix_ht' => 300000,
        ]);
        $missionAudit->collaborateurs()->attach($this->collaborateur->id);

        // Mission d'un autre membre du staff : ne compte pas pour ce collaborateur
        Mission::factory()->create([
            'entreprise_id' => Entreprise::factory()->create(['statut' => 'client'])->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $audit->id,
            'statut' => 'en_cours',
            'prix_ht' => 300000,
        ])->collaborateurs()->attach($this->admin->id);

        $rows = $this->actingAs($this->admin)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats?exercice_id=".$this->exercice->id)
            ->assertOk()
            ->json('data.missions_par_prestation');

        // Tri decroissant : Comptabilite (2) puis Audit legal (1)
        $this->assertCount(2, $rows);
        $this->assertSame('Comptabilité', $rows[0]['designation']);
        $this->assertSame(2, $rows[0]['total']);
        $this->assertSame('Audit legal', $rows[1]['designation']);
        $this->assertSame(1, $rows[1]['total']);
    }

    public function test_participation_via_tache_assignee_sans_equipe(): void
    {
        // Regle metier : a la creation d'une mission on n'affecte personne a
        // l'equipe — la participation reelle passe par les taches (US-45).
        // Mission terminee ou le collaborateur n'est PAS dans mission_user mais
        // possede une tache -> elle compte pour la prestation ET le realise.
        $mission = Mission::factory()->create([
            'entreprise_id' => Entreprise::factory()->create(['statut' => 'client'])->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
            'statut' => 'terminee',
            'date_fin' => now()->year.'-05-20',
            'prix_ht' => 120000,
        ]);
        Tache::factory()->create(['mission_id' => $mission->id, 'assigned_to' => $this->collaborateur->id, 'statut' => 'terminee']);

        $res = $this->actingAs($this->admin)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats?exercice_id=".$this->exercice->id)
            ->assertOk();

        $rows = $res->json('data.missions_par_prestation');
        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]['total']);

        // Le realise suit la meme definition de participation
        $this->assertEquals(120000.0, $res->json('data.realise.ca_ht'));
        $this->assertEquals(1, $res->json('data.realise.missions_cloturees'));
        $this->assertEquals(120000.0, $res->json('data.realise_mensuel.data')[4]); // mai -> index 4
    }

    public function test_participation_equipe_et_tache_comptee_une_seule_fois(): void
    {
        // Collaborateur membre de l'equipe ET assigne a une tache de la meme
        // mission : la mission ne doit compter qu'une fois (pas de doublon OR).
        $mission = $this->creerMissionTerminee($this->collaborateur, now()->year.'-03-10', 100000);
        Tache::factory()->create(['mission_id' => $mission->id, 'assigned_to' => $this->collaborateur->id, 'statut' => 'en_cours']);

        $res = $this->actingAs($this->admin)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats?exercice_id=".$this->exercice->id)
            ->assertOk();

        $rows = $res->json('data.missions_par_prestation');
        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]['total']);
        $this->assertEquals(1, $res->json('data.realise.missions_cloturees'));
    }

    public function test_missions_par_prestation_scope_exercice(): void
    {
        $autreExercice = Exercice::factory()->create(['annee' => now()->year - 1, 'statut' => 'cloture']);

        Mission::factory()->create([
            'entreprise_id' => Entreprise::factory()->create(['statut' => 'client'])->id,
            'exercice_id' => $autreExercice->id,
            'prestation_id' => $this->prestation->id,
            'statut' => 'en_cours',
            'prix_ht' => 100000,
        ])->collaborateurs()->attach($this->collaborateur->id);

        $rows = $this->actingAs($this->admin)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats?exercice_id=".$this->exercice->id)
            ->assertOk()
            ->json('data.missions_par_prestation');

        $this->assertSame([], $rows);
    }

    // -------------------------------------------------------------------------
    // Roles / autorisation
    // -------------------------------------------------------------------------

    public function test_admin_accede_aux_stats_de_nimporte_quel_collaborateur(): void
    {
        $this->actingAs($this->admin)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats")
            ->assertOk();

        $this->actingAs($this->admin)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->admin->id}/stats")
            ->assertOk();
    }

    public function test_collaborateur_accede_a_ses_propres_stats(): void
    {
        $this->actingAs($this->collaborateur)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats")
            ->assertOk();
    }

    public function test_collaborateur_refuse_sur_un_autre_collaborateur(): void
    {
        $autre = User::factory()->create();
        $autre->assignRole('collaborateur');

        $this->actingAs($this->collaborateur)
            ->getJson("/api/v1/kpi/collaborateurs/{$autre->id}/stats")
            ->assertForbidden();
    }

    public function test_secretaire_refuse(): void
    {
        $this->actingAs($this->secretaire)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats")
            ->assertForbidden();
    }

    public function test_cible_secretaire_retourne_404(): void
    {
        $this->actingAs($this->admin)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->secretaire->id}/stats")
            ->assertNotFound();
    }

    public function test_cible_client_retourne_404(): void
    {
        $client = User::factory()->create(['entreprise_id' => Entreprise::factory()->create()->id]);
        $client->assignRole('client');

        $this->actingAs($this->admin)
            ->getJson("/api/v1/kpi/collaborateurs/{$client->id}/stats")
            ->assertNotFound();
    }

    public function test_client_refuse(): void
    {
        $client = User::factory()->create(['entreprise_id' => Entreprise::factory()->create()->id]);
        $client->assignRole('client');

        $this->actingAs($client)
            ->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats")
            ->assertForbidden();
    }

    public function test_non_authentifie_recoit_401(): void
    {
        $this->getJson("/api/v1/kpi/collaborateurs/{$this->collaborateur->id}/stats")
            ->assertUnauthorized();
    }
}
