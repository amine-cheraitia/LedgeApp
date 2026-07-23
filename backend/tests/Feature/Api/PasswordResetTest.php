<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\ReinitialisationMotDePasseMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reinitialise le rate limiter (cache array) entre chaque test pour isoler le throttling.
        Cache::flush();
    }

    // -------------------------------------------------------------------------
    // Mot de passe oublie (forgot)
    // -------------------------------------------------------------------------

    public function test_forgot_password_envoie_un_lien_si_le_compte_existe(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'forgot@test.dz']);

        $response = $this->postJson('/api/v1/forgot-password', ['email' => 'forgot@test.dz']);

        $response->assertOk();

        // Le mail est mis en file (ShouldQueue) : envoi asynchrone anti-oracle temporel.
        Mail::assertQueued(
            ReinitialisationMotDePasseMail::class,
            fn (ReinitialisationMotDePasseMail $mail) => $mail->hasTo('forgot@test.dz')
        );
    }

    public function test_forgot_password_reponse_generique_si_compte_inconnu(): void
    {
        Mail::fake();

        // Reponse identique (200) qu'il existe ou non : anti-enumeration d'emails.
        $response = $this->postJson('/api/v1/forgot-password', ['email' => 'inconnu@test.dz']);

        $response->assertOk();

        Mail::assertNothingQueued();
    }

    public function test_forgot_password_est_throttle(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/forgot-password', ['email' => 'spam@test.dz']);
        }

        $response = $this->postJson('/api/v1/forgot-password', ['email' => 'spam@test.dz']);

        $response->assertStatus(429);
    }

    // -------------------------------------------------------------------------
    // Definition / reinitialisation (reset)
    // -------------------------------------------------------------------------

    public function test_reset_password_definit_le_mot_de_passe(): void
    {
        $user = User::factory()->create(['email' => 'reset@test.dz']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'reset@test.dz',
            'password' => 'NouveauPass123!',
            'password_confirmation' => 'NouveauPass123!',
        ]);

        $response->assertOk();

        $this->assertTrue(Hash::check('NouveauPass123!', $user->fresh()->password));
    }

    public function test_reset_password_rejette_un_token_invalide(): void
    {
        User::factory()->create(['email' => 'reset2@test.dz']);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => 'jeton-bidon',
            'email' => 'reset2@test.dz',
            'password' => 'NouveauPass123!',
            'password_confirmation' => 'NouveauPass123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('token');
    }

    public function test_reset_password_exige_un_mot_de_passe_robuste(): void
    {
        $user = User::factory()->create(['email' => 'reset3@test.dz']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'reset3@test.dz',
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_reset_password_exige_la_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'reset4@test.dz']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'reset4@test.dz',
            'password' => 'NouveauPass123!',
            'password_confirmation' => 'Different123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }
}
