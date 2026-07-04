<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Exercice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExerciceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_admin_liste_les_exercices(): void
    {
        Exercice::factory()->create(['annee' => 2025]);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/exercices')
            ->assertOk()
            ->assertJsonFragment(['annee' => 2025]);
    }

    public function test_admin_affiche_un_exercice(): void
    {
        $exercice = Exercice::factory()->create(['annee' => 2024]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/exercices/{$exercice->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $exercice->id)
            ->assertJsonPath('data.annee', 2024);
    }

    public function test_exercice_courant_renvoie_l_exercice_ouvert_de_l_annee(): void
    {
        $exercice = Exercice::factory()->create([
            'annee' => now()->year,
            'statut' => 'ouvert',
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/exercices/current')
            ->assertOk()
            ->assertJsonPath('data.id', $exercice->id)
            ->assertJsonPath('data.statut', 'ouvert');
    }
}
