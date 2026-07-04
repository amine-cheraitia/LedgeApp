<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reinitialise le rate limiter (cache array) entre chaque test pour isoler le throttling.
        Cache::flush();
    }

    public function test_login_reussit_avec_identifiants_valides(): void
    {
        User::factory()->create([
            'email' => 'login@test.dz',
            'password' => Hash::make('MotDePasse123!'),
        ]);

        // En-tete Origin d'un domaine stateful (Sanctum SPA) : declenche la session
        // cote requete, requise par $request->session()->regenerate() dans le controller.
        $response = $this->withHeader('Origin', config('app.url'))
            ->postJson('/api/v1/login', [
                'email' => 'login@test.dz',
                'password' => 'MotDePasse123!',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'login@test.dz');
    }

    public function test_login_echoue_avec_mauvais_identifiants(): void
    {
        User::factory()->create([
            'email' => 'login2@test.dz',
            'password' => Hash::make('MotDePasse123!'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'login2@test.dz',
            'password' => 'mauvais',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_est_limite_par_throttle(): void
    {
        // throttle:5,1 -> les 5 premieres tentatives passent (422 identifiants incorrects),
        // la 6e dans la meme minute est bloquee par le rate limiter (429).
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'brute@test.dz',
                'password' => 'mauvais',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/login', [
            'email' => 'brute@test.dz',
            'password' => 'mauvais',
        ])->assertStatus(429);
    }
}
