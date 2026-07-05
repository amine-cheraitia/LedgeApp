<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Avoir;
use App\Models\CategorieEntreprise;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\RegimeFiscal;
use App\Models\TvaTaux;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Complement de couverture cible sur des controllers/policies/requests
 * peu couverts : Prestation, Exercice, Auth, User, Commentaires, Avoir.
 */
class CoverageComplementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $secretaire;

    private User $collaborateur;

    private Entreprise $entreprise;

    private Prestation $prestation;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();

        // Isole le rate limiter (routes login/logout non concernees ici, mais par prudence).
        Cache::flush();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->secretaire = User::factory()->create();
        $this->secretaire->assignRole('secretaire');

        $this->collaborateur = User::factory()->create();
        $this->collaborateur->assignRole('collaborateur');

        // Referentiels necessaires au calcul du prix HT (Prestation::calculerPrixHt).
        RegimeFiscal::create(['code' => 'reel', 'designation' => 'Reel', 'indice' => 1.5]);
        CategorieEntreprise::create(['code' => 'PME', 'designation' => 'PME', 'indice' => 1.75]);

        $this->entreprise = Entreprise::factory()->create([
            'regime_fiscal' => 'reel',
            'categorie' => 'PME',
            'statut' => 'prospect',
        ]);

        $this->prestation = Prestation::create([
            'code' => 'ACMPT',
            'designation' => 'Assistance Comptable',
            'tarif_initial' => 120000,
            'duree_mois' => 12,
            'actif' => true,
        ]);

        $this->exercice = Exercice::create([
            'annee' => (int) date('Y'),
            'date_ouverture' => date('Y').'-01-01',
            'date_cloture' => date('Y').'-12-31',
            'statut' => 'ouvert',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function creerMission(?string $reference = null): Mission
    {
        return Mission::create([
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'exercice_id' => $this->exercice->id,
            'reference' => $reference ?? 'M'.date('Y').'-900',
            'prix_ht' => 315000,
            'date_debut' => date('Y').'-01-01',
            'date_fin' => date('Y').'-12-31',
            'statut' => 'en_cours',
        ]);
    }

    private function creerTacheId(): int
    {
        $missionId = $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_debut' => '2026-04-01',
                'date_fin' => '2027-03-31',
            ])->json('data.id');

        return $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$missionId}/taches", [
                'titre' => 'Tache de test',
            ])->json('data.id');
    }

    private function creerFacture(): Facture
    {
        $tvaTaux = TvaTaux::create([
            'taux' => 19,
            'designation' => 'TVA standard',
            'date_debut' => '2023-01-01',
            'type' => 'standard',
            'actif' => true,
        ]);

        $mission = $this->creerMission('M'.date('Y').'-950');

        return Facture::create([
            'entreprise_id' => $this->entreprise->id,
            'exercice_id' => $this->exercice->id,
            'mission_id' => $mission->id,
            'created_by' => $this->admin->id,
            'tva_taux_id' => $tvaTaux->id,
            'numero' => 'FF'.date('Y').'-950',
            'type' => 'FF',
            'date_facture' => date('Y').'-01-15',
            'date_echeance' => date('Y').'-03-01',
            'montant_ht' => 94500,
            'taux_tva' => 19,
            'montant_tva' => 17955,
            'montant_ttc' => 112455,
            'montant_paye' => 0,
            'statut_paiement' => 'en_attente',
            'mode_paiement' => 'non_defini',
        ]);
    }

    // -------------------------------------------------------------------------
    // Prestation — calcul de prix / update / delete / autorisations
    // -------------------------------------------------------------------------

    public function test_admin_peut_calculer_le_prix_ht(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/prestations/{$this->prestation->id}/calculer-prix", [
                'regime_fiscal' => 'reel',
                'categorie' => 'PME',
            ]);

        $response->assertOk();
        // 120 000 x 1.5 (reel) x 1.75 (PME) = 315 000
        $this->assertEquals(315000.0, (float) $response->json('prix_ht'));
        $this->assertEquals('ACMPT', $response->json('prestation'));
    }

    public function test_calculer_prix_requiert_regime_et_categorie(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/prestations/{$this->prestation->id}/calculer-prix", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['regime_fiscal', 'categorie']);
    }

    public function test_calculer_prix_interdit_au_collaborateur(): void
    {
        $this->actingAs($this->collaborateur)
            ->postJson("/api/v1/prestations/{$this->prestation->id}/calculer-prix", [
                'regime_fiscal' => 'reel',
                'categorie' => 'PME',
            ])
            ->assertForbidden();
    }

    public function test_admin_peut_modifier_une_prestation(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/prestations/{$this->prestation->id}", [
                'designation' => 'Assistance Comptable Premium',
                'tarif_initial' => 150000,
            ]);

        $response->assertOk();
        $this->assertEquals('Assistance Comptable Premium', $response->json('data.designation'));
        $this->assertDatabaseHas('prestations', [
            'id' => $this->prestation->id,
            'designation' => 'Assistance Comptable Premium',
        ]);
    }

    public function test_modifier_prestation_interdit_au_secretaire(): void
    {
        $this->actingAs($this->secretaire)
            ->putJson("/api/v1/prestations/{$this->prestation->id}", [
                'designation' => 'Hack',
            ])
            ->assertForbidden();
    }

    public function test_admin_peut_supprimer_une_prestation_sans_mission(): void
    {
        $prestation = Prestation::create([
            'code' => 'AUDIT',
            'designation' => 'Audit',
            'tarif_initial' => 90000,
            'duree_mois' => 6,
            'actif' => true,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/prestations/{$prestation->id}")
            ->assertOk();

        $this->assertDatabaseMissing('prestations', ['id' => $prestation->id]);
    }

    public function test_supprimer_prestation_liee_a_une_mission_retourne_409(): void
    {
        $this->creerMission();

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/prestations/{$this->prestation->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('prestations', ['id' => $this->prestation->id]);
    }

    public function test_supprimer_prestation_interdit_au_secretaire(): void
    {
        $this->actingAs($this->secretaire)
            ->deleteJson("/api/v1/prestations/{$this->prestation->id}")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Exercice — store / update / autorisations
    // -------------------------------------------------------------------------

    public function test_admin_peut_creer_un_exercice(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/exercices', [
                'annee' => 2030,
                'date_ouverture' => '2030-01-01',
                'date_cloture' => '2030-12-31',
                'statut' => 'ouvert',
            ]);

        $response->assertCreated();
        $this->assertEquals(2030, $response->json('data.annee'));
        $this->assertDatabaseHas('exercices', ['annee' => 2030, 'statut' => 'ouvert']);
    }

    public function test_creer_exercice_validation_champs_requis(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/exercices', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['annee', 'date_ouverture', 'date_cloture', 'statut']);
    }

    public function test_creer_exercice_refuse_annee_dupliquee(): void
    {
        // L'annee courante est deja creee dans setUp().
        $this->actingAs($this->admin)
            ->postJson('/api/v1/exercices', [
                'annee' => (int) date('Y'),
                'date_ouverture' => date('Y').'-01-01',
                'date_cloture' => date('Y').'-12-31',
                'statut' => 'ouvert',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['annee']);
    }

    public function test_admin_peut_modifier_un_exercice(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/exercices/{$this->exercice->id}", [
                'statut' => 'cloture',
            ]);

        $response->assertOk();
        $this->assertEquals('cloture', $response->json('data.statut'));
        $this->assertDatabaseHas('exercices', [
            'id' => $this->exercice->id,
            'statut' => 'cloture',
        ]);
    }

    public function test_creer_exercice_interdit_au_secretaire(): void
    {
        $this->actingAs($this->secretaire)
            ->postJson('/api/v1/exercices', [
                'annee' => 2031,
                'date_ouverture' => '2031-01-01',
                'date_cloture' => '2031-12-31',
                'statut' => 'ouvert',
            ])
            ->assertForbidden();
    }

    public function test_modifier_exercice_interdit_au_collaborateur(): void
    {
        $this->actingAs($this->collaborateur)
            ->putJson("/api/v1/exercices/{$this->exercice->id}", [
                'statut' => 'cloture',
            ])
            ->assertForbidden();
    }

    public function test_supprimer_exercice_interdit_au_secretaire(): void
    {
        // La route DELETE exercices est reservee a l'admin (middleware role:admin).
        $this->actingAs($this->secretaire)
            ->deleteJson("/api/v1/exercices/{$this->exercice->id}")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Auth — logout / me
    // -------------------------------------------------------------------------

    public function test_logout_deconnecte_l_utilisateur(): void
    {
        $this->actingAs($this->admin)
            ->withHeader('Origin', config('app.url'))
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Déconnecté.');
    }

    public function test_me_retourne_l_utilisateur_et_ses_roles(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('user.email', $this->admin->email);

        $this->assertContains('admin', array_column($response->json('user.roles'), 'name'));
    }

    // -------------------------------------------------------------------------
    // User — index / show / suppression (UserPolicy)
    // -------------------------------------------------------------------------

    public function test_admin_peut_lister_les_utilisateurs(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/users');

        $response->assertOk();
        // admin + secretaire + collaborateur crees dans setUp()
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_admin_peut_voir_un_utilisateur(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/users/{$this->collaborateur->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $this->collaborateur->id)
            ->assertJsonPath('data.email', $this->collaborateur->email);
    }

    public function test_admin_peut_supprimer_un_autre_utilisateur(): void
    {
        $cible = User::factory()->create();
        $cible->assignRole('collaborateur');

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/users/{$cible->id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $cible->id]);
    }

    public function test_admin_ne_peut_pas_se_supprimer_lui_meme(): void
    {
        // UserPolicy::delete interdit l'auto-suppression meme a un admin.
        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/users/{$this->admin->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_creer_utilisateur_interdit_au_collaborateur(): void
    {
        $this->actingAs($this->collaborateur)
            ->postJson('/api/v1/users', [
                'name' => 'Nouveau',
                'email' => 'nouveau@ledge.dz',
                'role' => 'collaborateur',
            ])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Commentaires de tache — create / list / delete / autorisations
    // -------------------------------------------------------------------------

    public function test_admin_peut_creer_un_commentaire(): void
    {
        $tacheId = $this->creerTacheId();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/taches/{$tacheId}/commentaires", [
                'contenu' => 'Premier commentaire',
            ])
            ->assertCreated()
            ->assertJsonPath('data.contenu', 'Premier commentaire');
    }

    public function test_admin_peut_lister_les_commentaires(): void
    {
        $tacheId = $this->creerTacheId();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/taches/{$tacheId}/commentaires", ['contenu' => 'A']);
        $this->actingAs($this->admin)
            ->postJson("/api/v1/taches/{$tacheId}/commentaires", ['contenu' => 'B']);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/taches/{$tacheId}/commentaires");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_peut_supprimer_un_commentaire(): void
    {
        $tacheId = $this->creerTacheId();

        $commentaireId = $this->actingAs($this->admin)
            ->postJson("/api/v1/taches/{$tacheId}/commentaires", ['contenu' => 'A supprimer'])
            ->json('data.id');

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/taches/{$tacheId}/commentaires/{$commentaireId}")
            ->assertNoContent();

        // TacheCommentaire utilise SoftDeletes : la ligne est marquee supprimee.
        $this->assertSoftDeleted('tache_commentaires', ['id' => $commentaireId]);
    }

    public function test_client_ne_peut_pas_acceder_aux_commentaires(): void
    {
        $tacheId = $this->creerTacheId();

        $client = User::factory()->create(['entreprise_id' => $this->entreprise->id]);
        $client->assignRole('client');

        // Le middleware backoffice interdit toute l'aire back-office aux clients.
        $this->actingAs($client)
            ->getJson("/api/v1/taches/{$tacheId}/commentaires")
            ->assertForbidden();
    }

    public function test_collaborateur_ne_peut_pas_supprimer_le_commentaire_d_autrui(): void
    {
        // Tache affectee au collaborateur (il peut la voir), mais le commentaire est de l'admin.
        $missionId = $this->actingAs($this->admin)
            ->postJson('/api/v1/missions', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_debut' => '2026-04-01',
                'date_fin' => '2027-03-31',
            ])->json('data.id');

        $tacheId = $this->actingAs($this->admin)
            ->postJson("/api/v1/missions/{$missionId}/taches", [
                'titre' => 'Tache collab',
                'assigned_to' => $this->collaborateur->id,
            ])->json('data.id');

        $commentaireId = $this->actingAs($this->admin)
            ->postJson("/api/v1/taches/{$tacheId}/commentaires", ['contenu' => 'Commentaire admin'])
            ->json('data.id');

        $this->actingAs($this->collaborateur)
            ->deleteJson("/api/v1/taches/{$tacheId}/commentaires/{$commentaireId}")
            ->assertForbidden();

        $this->actingAs($this->collaborateur)
            ->putJson("/api/v1/taches/{$tacheId}/commentaires/{$commentaireId}", ['contenu' => 'Modif'])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Avoir — suppression (AvoirPolicy)
    // -------------------------------------------------------------------------

    public function test_admin_peut_supprimer_un_avoir(): void
    {
        $facture = $this->creerFacture();

        $avoir = Avoir::create([
            'facture_origine_id' => $facture->id,
            'exercice_id' => $this->exercice->id,
            'created_by' => $this->admin->id,
            'numero' => 'FA'.date('Y').'-001',
            'date_avoir' => date('Y').'-02-01',
            'montant_ht' => 10000,
            'taux_tva_snapshot' => 19,
            'montant_tva' => 1900,
            'montant_ttc' => 11900,
            'motif' => 'Correction erreur de facturation',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/avoirs/{$avoir->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('avoirs', ['id' => $avoir->id]);
    }

    public function test_supprimer_avoir_interdit_au_secretaire(): void
    {
        $facture = $this->creerFacture();

        $avoir = Avoir::create([
            'facture_origine_id' => $facture->id,
            'exercice_id' => $this->exercice->id,
            'created_by' => $this->admin->id,
            'numero' => 'FA'.date('Y').'-002',
            'date_avoir' => date('Y').'-02-01',
            'montant_ht' => 10000,
            'taux_tva_snapshot' => 19,
            'montant_tva' => 1900,
            'montant_ttc' => 11900,
            'motif' => 'Correction erreur de facturation',
        ]);

        $this->actingAs($this->secretaire)
            ->deleteJson("/api/v1/avoirs/{$avoir->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('avoirs', ['id' => $avoir->id]);
    }
}
