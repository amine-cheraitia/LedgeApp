<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CategorieEntreprise;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\RegimeFiscal;
use App\Models\TacheCommentaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TacheApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $missionId;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $entreprise = Entreprise::factory()->create([
            'regime_fiscal' => 'forfait',
            'categorie' => 'TPE',
            'statut' => 'prospect',
        ]);

        $prestation = Prestation::create([
            'code' => 'ACMPT',
            'designation' => 'Assistance Comptable',
            'tarif_initial' => 120000,
            'duree_mois' => 12,
            'actif' => true,
        ]);

        RegimeFiscal::create(['code' => 'forfait', 'designation' => 'Forfait', 'indice' => 1.0]);
        CategorieEntreprise::create(['code' => 'TPE', 'designation' => 'TPE', 'indice' => 1.0]);

        Exercice::create([
            'annee' => (int) date('Y'),
            'date_ouverture' => date('Y').'-01-01',
            'date_cloture' => date('Y').'-12-31',
            'statut' => 'ouvert',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $entreprise->id,
                'prestation_id' => $prestation->id,
                'date_debut' => '2026-04-01',
                'date_fin' => '2027-03-31',
            ]);

        $this->missionId = $response->json('data.id');
    }

    public function test_can_create_tache(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Collecte documents',
                'description' => 'Recuperer les documents comptables',
                'priorite' => 2,
            ]);

        $response->assertCreated();
        $this->assertEquals('Collecte documents', $response->json('data.titre'));
        $this->assertEquals('a_faire', $response->json('data.statut'));
    }

    public function test_acceder_a_une_tache_via_une_autre_mission_renvoie_404(): void
    {
        // La tache appartient a la mission de setUp ; y acceder via l'URL d'une autre mission -> 404.
        $tacheId = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", ['titre' => 'Tache A', 'priorite' => 2])
            ->json('data.id');

        $autreEntreprise = Entreprise::factory()->create([
            'regime_fiscal' => 'forfait', 'categorie' => 'TPE', 'statut' => 'prospect',
        ]);
        $autreMissionId = $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $autreEntreprise->id,
                'prestation_id' => Prestation::where('code', 'ACMPT')->value('id'),
                'date_debut' => '2026-04-01',
                'date_fin' => '2027-03-31',
            ])->json('data.id');

        $this->actingAs($this->admin)
            ->getJson("/api/v1/missions/{$autreMissionId}/taches/{$tacheId}")
            ->assertNotFound();
    }

    public function test_can_list_taches(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Tache 1',
            ]);
        $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Tache 2',
            ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/missions/{$this->missionId}/taches");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_update_tache_statut(): void
    {
        $createResponse = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Ma tache',
            ]);

        $tacheId = $createResponse->json('data.id');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/missions/{$this->missionId}/taches/{$tacheId}", [
                'titre' => 'Ma tache',
                'statut' => 'terminee',
            ]);

        $response->assertOk();
        $this->assertEquals('terminee', $response->json('data.statut'));
    }

    public function test_can_delete_tache_sans_commentaires(): void
    {
        $tacheId = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", ['titre' => 'A supprimer'])
            ->json('data.id');

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/missions/{$this->missionId}/taches/{$tacheId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('taches', ['id' => $tacheId, 'deleted_at' => null]);
    }

    public function test_cannot_delete_tache_avec_commentaires(): void
    {
        $tacheId = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", ['titre' => 'Avec commentaire'])
            ->json('data.id');

        // Ajouter un commentaire directement via le modèle
        TacheCommentaire::create([
            'tache_id' => $tacheId,
            'user_id' => $this->admin->id,
            'contenu' => 'Mon commentaire',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/missions/{$this->missionId}/taches/{$tacheId}")
            ->assertStatus(409);
    }

    public function test_collaborateur_ne_voit_que_ses_propres_taches(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');

        // Attacher le collaborateur a la mission (mission_user)
        Mission::find($this->missionId)->collaborateurs()->attach($collaborateur->id);

        // Tâche assignée au collaborateur
        $this->actingAs($this->admin)->postJson("/api/v1/missions/{$this->missionId}/taches", [
            'titre' => 'Pour collab',
            'assigned_to' => $collaborateur->id,
        ]);

        // Tâche non assignée (affectée à personne)
        $this->actingAs($this->admin)->postJson("/api/v1/missions/{$this->missionId}/taches", [
            'titre' => 'Non assignee',
        ]);

        // Le collaborateur ne voit QUE sa tâche (pas celles des autres)
        $response = $this->actingAs($collaborateur)
            ->getJson("/api/v1/missions/{$this->missionId}/taches");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Pour collab', $response->json('data.0.titre'));
    }

    public function test_admin_voit_toutes_les_taches_de_la_mission(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');

        $this->actingAs($this->admin)->postJson("/api/v1/missions/{$this->missionId}/taches", [
            'titre' => 'Pour collab',
            'assigned_to' => $collaborateur->id,
        ]);
        $this->actingAs($this->admin)->postJson("/api/v1/missions/{$this->missionId}/taches", [
            'titre' => 'Non assignee',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/missions/{$this->missionId}/taches");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_collaborateur_ne_peut_pas_voir_une_tache_non_assignee(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');
        Mission::find($this->missionId)->collaborateurs()->attach($collaborateur->id);

        // Tâche non affectée au collaborateur
        $autreTacheId = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", ['titre' => 'Tache autrui'])
            ->json('data.id');

        $this->actingAs($collaborateur)
            ->getJson("/api/v1/missions/{$this->missionId}/taches/{$autreTacheId}")
            ->assertForbidden();
    }

    public function test_collaborateur_ne_peut_pas_commenter_une_tache_non_assignee(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');
        Mission::find($this->missionId)->collaborateurs()->attach($collaborateur->id);

        $tacheId = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", ['titre' => 'Tache autrui'])
            ->json('data.id');

        $this->actingAs($collaborateur)
            ->postJson("/api/v1/taches/{$tacheId}/commentaires", ['contenu' => 'Coucou'])
            ->assertForbidden();
    }

    public function test_collaborateur_peut_commenter_sa_tache(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');

        $tacheId = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Ma tache',
                'assigned_to' => $collaborateur->id,
            ])->json('data.id');

        $this->actingAs($collaborateur)
            ->postJson("/api/v1/taches/{$tacheId}/commentaires", ['contenu' => 'Je commence'])
            ->assertCreated();
    }

    public function test_collaborateur_ne_peut_ni_modifier_ni_supprimer_le_commentaire_d_un_admin(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');

        $tacheId = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Ma tache',
                'assigned_to' => $collaborateur->id,
            ])->json('data.id');

        // Commentaire laissé par l'admin sur la tâche du collaborateur
        $commentaireId = $this->actingAs($this->admin)
            ->postJson("/api/v1/taches/{$tacheId}/commentaires", ['contenu' => 'Commentaire admin'])
            ->json('data.id');

        // Le collaborateur (affecté) ne peut ni modifier ni supprimer ce commentaire
        $this->actingAs($collaborateur)
            ->putJson("/api/v1/taches/{$tacheId}/commentaires/{$commentaireId}", ['contenu' => 'Modif'])
            ->assertForbidden();

        $this->actingAs($collaborateur)
            ->deleteJson("/api/v1/taches/{$tacheId}/commentaires/{$commentaireId}")
            ->assertForbidden();
    }

    public function test_tache_statut_initial_est_a_faire(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", ['titre' => 'Nouvelle tache']);

        $this->assertEquals('a_faire', $response->json('data.statut'));
    }

    public function test_commentaire_sur_tache_a_faire_la_passe_en_cours(): void
    {
        $tacheId = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", ['titre' => 'Ma tache'])
            ->json('data.id');

        // La tache est initialement a_faire ; le 1er commentaire doit l'engager
        $this->actingAs($this->admin)
            ->postJson("/api/v1/taches/{$tacheId}/commentaires", ['contenu' => 'Premier commentaire'])
            ->assertCreated();

        $this->assertDatabaseHas('taches', ['id' => $tacheId, 'statut' => 'en_cours']);
    }

    public function test_commentaire_sur_tache_terminee_ne_change_pas_le_statut(): void
    {
        $tacheId = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", ['titre' => 'Ma tache'])
            ->json('data.id');

        $this->actingAs($this->admin)
            ->putJson("/api/v1/missions/{$this->missionId}/taches/{$tacheId}", [
                'titre' => 'Ma tache',
                'statut' => 'terminee',
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/taches/{$tacheId}/commentaires", ['contenu' => 'Un commentaire'])
            ->assertCreated();

        $this->assertDatabaseHas('taches', ['id' => $tacheId, 'statut' => 'terminee']);
    }

    public function test_validation_titre_requis(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['titre']);
    }

    public function test_peut_creer_tache_avec_date_debut(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Tache datee',
                'date_debut' => '2026-05-01',
                'date_echeance' => '2026-05-15',
            ]);

        $response->assertCreated();
        $this->assertEquals('2026-05-01', $response->json('data.date_debut'));
        $this->assertEquals('2026-05-15', $response->json('data.date_echeance'));
    }

    public function test_date_debut_posterieure_a_echeance_est_rejetee(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Dates incoherentes',
                'date_debut' => '2026-05-20',
                'date_echeance' => '2026-05-10',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_debut']);
    }

    public function test_assignation_a_un_secretaire_est_rejetee(): void
    {
        $secretaire = User::factory()->create();
        $secretaire->assignRole('secretaire');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Pour secretaire',
                'assigned_to' => $secretaire->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_to']);
    }

    public function test_assignation_a_un_collaborateur_est_acceptee(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Pour collaborateur',
                'assigned_to' => $collaborateur->id,
            ])
            ->assertCreated();
    }

    public function test_date_debut_avant_debut_mission_est_rejetee(): void
    {
        // Mission : date_debut = 2026-04-01
        $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Trop tot',
                'date_debut' => '2026-03-31',
                'date_echeance' => '2026-04-15',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_debut']);
    }

    public function test_date_echeance_apres_fin_mission_est_rejetee_si_mission_a_lheure(): void
    {
        // Mission : date_fin = 2027-03-31 (dans le futur → mission a l'heure)
        $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Trop tard',
                'date_debut' => '2027-03-01',
                'date_echeance' => '2027-04-15',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_echeance']);
    }

    public function test_date_echeance_apres_fin_autorisee_si_mission_en_retard(): void
    {
        // Mission dont la fin planifiee est deja depassee → echeance libre au-dela.
        $missionRetard = Mission::factory()->create([
            'entreprise_id' => Entreprise::first()->id,
            'exercice_id' => Exercice::first()->id,
            'date_debut' => '2024-01-01',
            'date_fin' => '2024-06-30',
            'statut' => 'en_cours',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$missionRetard->id}/taches", [
                'titre' => 'Rattrapage',
                'date_debut' => '2024-02-01',
                'date_echeance' => '2026-12-31',
            ])
            ->assertCreated();
    }

    // ─── Priorite : borne 4 niveaux (Faible/Normale/Haute/Urgente) ───────────────

    public function test_priorite_superieure_a_4_est_rejetee(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Priorite hors borne',
                'priorite' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['priorite']);
    }

    public function test_priorite_4_urgente_est_acceptee(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$this->missionId}/taches", [
                'titre' => 'Tache urgente',
                'priorite' => 4,
            ])
            ->assertCreated()
            ->assertJsonPath('data.priorite', 4);
    }

    // ─── Detection de conflit d'affectation ──────────────────────────────────────

    private function creerTacheCollaborateur(int $collaborateurId, string $debut, string $echeance, ?int $missionId = null): int
    {
        return $this->actingAs($this->admin)
            ->postJson('/api/v1/missions/'.($missionId ?? $this->missionId).'/taches', [
                'titre' => 'Tache collaborateur',
                'assigned_to' => $collaborateurId,
                'date_debut' => $debut,
                'date_echeance' => $echeance,
            ])
            ->json('data.id');
    }

    public function test_conflits_detecte_un_chevauchement(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');

        $this->creerTacheCollaborateur($collaborateur->id, '2026-06-01', '2026-06-05');

        $response = $this->actingAs($this->admin)->getJson('/api/v1/taches/conflits?'.http_build_query([
            'collaborateur_id' => $collaborateur->id,
            'date_debut' => '2026-06-03',
            'date_echeance' => '2026-06-07',
        ]));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_conflits_aucun_hors_periode(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');

        $this->creerTacheCollaborateur($collaborateur->id, '2026-06-01', '2026-06-05');

        $response = $this->actingAs($this->admin)->getJson('/api/v1/taches/conflits?'.http_build_query([
            'collaborateur_id' => $collaborateur->id,
            'date_debut' => '2026-06-10',
            'date_echeance' => '2026-06-15',
        ]));

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_conflits_exclut_la_tache_courante(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');

        $tacheId = $this->creerTacheCollaborateur($collaborateur->id, '2026-06-01', '2026-06-05');

        // En edition de cette meme tache : elle ne doit pas se signaler elle-meme.
        $response = $this->actingAs($this->admin)->getJson('/api/v1/taches/conflits?'.http_build_query([
            'collaborateur_id' => $collaborateur->id,
            'date_debut' => '2026-06-01',
            'date_echeance' => '2026-06-05',
            'exclude_tache_id' => $tacheId,
        ]));

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_conflits_detecte_a_travers_les_missions(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');

        // Tache existante dans une AUTRE mission, meme collaborateur.
        $autreMission = Mission::factory()->create([
            'entreprise_id' => Entreprise::first()->id,
            'exercice_id' => Exercice::first()->id,
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31',
            'statut' => 'en_cours',
        ]);
        $this->creerTacheCollaborateur($collaborateur->id, '2026-06-01', '2026-06-05', $autreMission->id);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/taches/conflits?'.http_build_query([
            'collaborateur_id' => $collaborateur->id,
            'date_debut' => '2026-06-03',
            'date_echeance' => '2026-06-07',
        ]));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_conflits_collaborateur_id_requis(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/taches/conflits?'.http_build_query([
                'date_debut' => '2026-06-03',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['collaborateur_id']);
    }

    public function test_conflits_au_moins_une_date_requise(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');

        $this->actingAs($this->admin)
            ->getJson('/api/v1/taches/conflits?'.http_build_query([
                'collaborateur_id' => $collaborateur->id,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_debut']);
    }

    public function test_conflits_interdit_au_collaborateur(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');

        $this->actingAs($collaborateur)
            ->getJson('/api/v1/taches/conflits?'.http_build_query([
                'collaborateur_id' => $collaborateur->id,
                'date_debut' => '2026-06-03',
                'date_echeance' => '2026-06-07',
            ]))
            ->assertForbidden();
    }
}
