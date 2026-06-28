<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\InvitationCompteMail;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortailAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Entreprise $entreprise;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->entreprise = Entreprise::factory()->create([
            'statut' => 'client',
        ]);
    }

    // -------------------------------------------------------------------------
    // Activation portail
    // -------------------------------------------------------------------------

    public function test_activation_cree_user_avec_role_client(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$this->entreprise->id}/activer-portail", [
                'name' => 'Contact Client',
                'email' => 'client@test.dz',
            ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'client@test.dz');

        $this->assertDatabaseHas('users', [
            'email' => 'client@test.dz',
            'entreprise_id' => $this->entreprise->id,
            'portail_actif' => true,
        ]);

        $user = User::where('email', 'client@test.dz')->first();
        $this->assertTrue($user->hasRole('client'));
    }

    public function test_activation_envoie_invitation_sans_mot_de_passe(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$this->entreprise->id}/activer-portail", [
                'name' => 'Contact Client',
                'email' => 'client2@test.dz',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'user', 'invitation_url']);

        // Aucun mot de passe (clair ou temporaire) n'est jamais expose a l'admin.
        $this->assertNull($response->json('temporary_password'));
        $this->assertNull($response->json('password'));
        $this->assertNotEmpty($response->json('invitation_url'));

        Mail::assertSent(InvitationCompteMail::class, fn (InvitationCompteMail $mail) => $mail->hasTo('client2@test.dz'));
    }

    public function test_renvoyer_invitation_au_client_existant(): void
    {
        Mail::fake();

        $clientUser = User::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'portail_actif' => true,
        ]);
        $clientUser->assignRole('client');

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$this->entreprise->id}/renvoyer-invitation");

        $response->assertOk()
            ->assertJsonStructure(['message', 'invitation_url']);

        Mail::assertSent(InvitationCompteMail::class, fn (InvitationCompteMail $mail) => $mail->hasTo($clientUser->email));
    }

    public function test_activation_echoue_si_statut_prospect(): void
    {
        $prospect = Entreprise::factory()->create(['statut' => 'prospect']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$prospect->id}/activer-portail", [
                'name' => 'Contact',
                'email' => 'prospect@test.dz',
            ]);

        $response->assertUnprocessable();
    }

    public function test_activation_echoue_si_portail_deja_active(): void
    {
        // Premier acces
        $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$this->entreprise->id}/activer-portail", [
                'name' => 'Contact',
                'email' => 'premier@test.dz',
            ]);

        // Deuxieme tentative
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$this->entreprise->id}/activer-portail", [
                'name' => 'Autre Contact',
                'email' => 'deuxieme@test.dz',
            ]);

        $response->assertStatus(409);
    }

    // -------------------------------------------------------------------------
    // Toggle portail
    // -------------------------------------------------------------------------

    public function test_toggle_desactive_portail_actif(): void
    {
        $clientUser = User::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'portail_actif' => true,
        ]);
        $clientUser->assignRole('client');

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$this->entreprise->id}/toggle-portail");

        $response->assertOk()
            ->assertJsonPath('portail_actif', false);

        $this->assertDatabaseHas('users', [
            'id' => $clientUser->id,
            'portail_actif' => false,
        ]);
    }

    public function test_toggle_reative_portail_desactive(): void
    {
        $clientUser = User::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'portail_actif' => false,
        ]);
        $clientUser->assignRole('client');

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$this->entreprise->id}/toggle-portail");

        $response->assertOk()
            ->assertJsonPath('portail_actif', true);
    }

    // -------------------------------------------------------------------------
    // Middleware EnsurePortailAccess
    // -------------------------------------------------------------------------

    public function test_middleware_bloque_staff(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/portail/me');

        $response->assertForbidden();
    }

    public function test_middleware_bloque_client_portail_desactive(): void
    {
        $clientUser = User::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'portail_actif' => false,
        ]);
        $clientUser->assignRole('client');

        $response = $this->actingAs($clientUser)
            ->getJson('/api/v1/portail/me');

        $response->assertForbidden();
    }

    public function test_middleware_autorise_client_actif(): void
    {
        $clientUser = User::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'portail_actif' => true,
        ]);
        $clientUser->assignRole('client');

        $response = $this->actingAs($clientUser)
            ->getJson('/api/v1/portail/me');

        $response->assertOk()
            ->assertJsonPath('data.email', $clientUser->email);
    }

    public function test_portail_me_retourne_entreprise_du_client(): void
    {
        $clientUser = User::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'portail_actif' => true,
        ]);
        $clientUser->assignRole('client');

        $response = $this->actingAs($clientUser)
            ->getJson('/api/v1/portail/me');

        $response->assertOk()
            ->assertJsonPath('data.entreprise.id', $this->entreprise->id)
            ->assertJsonPath('data.entreprise.raison_sociale', $this->entreprise->raison_sociale);
    }

    public function test_non_authentifie_ne_peut_pas_acceder_portail(): void
    {
        $response = $this->getJson('/api/v1/portail/me');

        $response->assertUnauthorized();
    }
}
