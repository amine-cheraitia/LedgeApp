<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Mission;
use App\Models\Prestation;
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

    public function test_exercice_courant_sans_exercice_ouvert_renvoie_data_null(): void
    {
        // Aucun exercice ouvert pour l'annee : reponse vide exploitable (pas de 500).
        $this->actingAs($this->admin)
            ->getJson('/api/v1/exercices/current')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_admin_supprime_un_exercice_sans_documents(): void
    {
        $exercice = Exercice::factory()->create(['annee' => 2019, 'statut' => 'cloture']);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/exercices/{$exercice->id}")
            ->assertOk();

        $this->assertDatabaseMissing('exercices', ['id' => $exercice->id]);
    }

    public function test_suppression_exercice_avec_mission_refusee(): void
    {
        $exercice = Exercice::factory()->create(['annee' => 2023]);
        $entreprise = Entreprise::factory()->create();
        $prestation = Prestation::create([
            'code' => 'ACMPT', 'designation' => 'Compta', 'tarif_initial' => 120000, 'duree_mois' => 12, 'actif' => true,
        ]);
        Mission::create([
            'entreprise_id' => $entreprise->id,
            'prestation_id' => $prestation->id,
            'exercice_id' => $exercice->id,
            'reference' => 'M2023-001',
            'prix_ht' => 315000,
            'date_debut' => '2023-01-01',
            'date_fin' => '2023-12-31',
            'statut' => 'en_cours',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/exercices/{$exercice->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('exercices', ['id' => $exercice->id]);
    }
}
