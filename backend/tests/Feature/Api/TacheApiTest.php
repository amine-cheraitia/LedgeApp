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

    public function test_collaborateur_ne_voit_que_ses_taches_assignees(): void
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

        // Tâche non assignée
        $this->actingAs($this->admin)->postJson("/api/v1/missions/{$this->missionId}/taches", [
            'titre' => 'Non assignee',
        ]);

        // Le collaborateur peut lister toutes les tâches de sa mission (US-45)
        $response = $this->actingAs($collaborateur)
            ->getJson("/api/v1/missions/{$this->missionId}/taches");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
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
}
