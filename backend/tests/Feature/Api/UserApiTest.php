<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $collaborateur;

    private User $secretaire;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);

        $this->admin = User::factory()->create(['name' => 'Admin Test']);
        $this->admin->assignRole('admin');

        $this->collaborateur = User::factory()->create(['name' => 'Collab Test']);
        $this->collaborateur->assignRole('collaborateur');

        $this->secretaire = User::factory()->create(['name' => 'Secretaire Test']);
        $this->secretaire->assignRole('secretaire');

        $entreprise = Entreprise::factory()->create();
        $this->client = User::factory()->create(['name' => 'Client Test', 'entreprise_id' => $entreprise->id]);
        $this->client->assignRole('client');
    }

    public function test_admin_obtient_l_annuaire_complet_avec_donnees_sensibles(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonCount(4, 'data')                       // admin + collaborateur + secretaire + client
            ->assertJsonFragment(['email' => $this->client->email]); // champs sensibles exposes a l'admin
    }

    public function test_collaborateur_ne_voit_que_le_personnel_sans_donnees_sensibles(): void
    {
        $response = $this->actingAs($this->collaborateur)->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonCount(3, 'data')                       // le client est exclu
            ->assertJsonMissingPath('data.0.email')            // vue minimale : pas d'email
            ->assertJsonMissingPath('data.0.entreprise_id')
            ->assertJsonMissing(['name' => 'Client Test']);    // aucun client dans la liste
    }

    public function test_secretaire_ne_voit_que_le_personnel_sans_donnees_sensibles(): void
    {
        $response = $this->actingAs($this->secretaire)->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissing(['name' => 'Client Test']);
    }

    public function test_recherche_par_nom_ne_fuit_pas_les_clients_au_personnel(): void
    {
        // Regression : la recherche par nom ne doit pas contourner la restriction de
        // role. Sans groupement du OR, "name LIKE ? OR email LIKE ? AND role(staff)"
        // laissait remonter un client dont le nom matche pour un non-admin.
        $response = $this->actingAs($this->collaborateur)
            ->getJson('/api/v1/users?search=Client');

        $response->assertOk()
            ->assertJsonMissing(['name' => 'Client Test']); // le client reste exclu
    }

    public function test_show_utilisateur_reserve_a_l_admin(): void
    {
        $this->actingAs($this->admin)
            ->getJson("/api/v1/users/{$this->collaborateur->id}")
            ->assertOk()
            ->assertJsonPath('data.email', $this->collaborateur->email);

        // Un collaborateur ne peut pas consulter la fiche complete d'un utilisateur.
        $this->actingAs($this->collaborateur)
            ->getJson("/api/v1/users/{$this->admin->id}")
            ->assertForbidden();
    }

    public function test_liste_utilisateurs_refusee_au_client(): void
    {
        $this->actingAs($this->client)
            ->getJson('/api/v1/users')
            ->assertForbidden();
    }
}
