<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\InvitationCompteMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_creation_user_envoie_une_invitation_sans_mot_de_passe(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)->postJson('/api/v1/users', [
            'name' => 'Nouveau Collaborateur',
            'email' => 'collab@test.dz',
            'role' => 'collaborateur',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email'],
                'invitation_url',
            ]);

        // L'admin ne recoit jamais de mot de passe.
        $this->assertNull($response->json('password'));
        $this->assertNull($response->json('data.password'));
        $this->assertNotEmpty($response->json('invitation_url'));

        $this->assertDatabaseHas('users', ['email' => 'collab@test.dz']);
        $user = User::where('email', 'collab@test.dz')->first();
        $this->assertTrue($user->hasRole('collaborateur'));

        Mail::assertSent(
            InvitationCompteMail::class,
            fn (InvitationCompteMail $mail) => $mail->hasTo('collab@test.dz')
        );
    }

    public function test_creation_user_n_accepte_plus_de_champ_password(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)->postJson('/api/v1/users', [
            'name' => 'Sans Password',
            'email' => 'sanspass@test.dz',
            'role' => 'collaborateur',
            'password' => 'IgnoreMoi123!',
        ])->assertCreated();

        // Le mot de passe fourni est ignore : le compte n'est utilisable qu'apres invitation.
        $user = User::where('email', 'sanspass@test.dz')->first();
        $this->assertFalse(Hash::check('IgnoreMoi123!', $user->password));
    }

    public function test_modification_user_conserve_son_email(): void
    {
        $user = User::factory()->create(['email' => 'edit@test.dz', 'name' => 'Ancien']);
        $user->assignRole('collaborateur');

        $response = $this->actingAs($this->admin)->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Nouveau Nom',
            'email' => 'edit@test.dz', // meme email : la regle unique doit ignorer l'utilisateur courant
            'role' => 'secretaire',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Nouveau Nom');
        $this->assertTrue($user->fresh()->hasRole('secretaire'));
    }

    public function test_creation_user_exige_un_role(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/users', [
            'name' => 'Sans Role',
            'email' => 'sansrole@test.dz',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_renvoyer_invitation_a_un_user(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'staff@test.dz']);
        $user->assignRole('collaborateur');

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/users/{$user->id}/renvoyer-invitation");

        $response->assertOk()
            ->assertJsonStructure(['message', 'invitation_url']);

        Mail::assertSent(
            InvitationCompteMail::class,
            fn (InvitationCompteMail $mail) => $mail->hasTo('staff@test.dz')
        );
    }

    public function test_non_admin_ne_peut_pas_creer_user(): void
    {
        $collab = User::factory()->create();
        $collab->assignRole('collaborateur');

        $response = $this->actingAs($collab)->postJson('/api/v1/users', [
            'name' => 'Tentative',
            'email' => 'tentative@test.dz',
            'role' => 'collaborateur',
        ]);

        $response->assertForbidden();
    }
}
