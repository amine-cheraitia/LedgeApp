<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Facture;
use App\Models\TvaTaux;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TvaTauxApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $secretaire;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'secretaire']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->secretaire = User::factory()->create();
        $this->secretaire->assignRole('secretaire');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'taux' => 19,
            'designation' => 'TVA standard',
            'type' => 'standard',
            'date_debut' => '2024-01-01',
            'date_fin' => null,
            'actif' => true,
        ], $overrides);
    }

    public function test_admin_peut_lister_les_taux(): void
    {
        TvaTaux::create($this->payload());

        $this->actingAs($this->admin)
            ->getJson('/api/v1/referentiels/tva-taux')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'taux', 'type', 'date_debut', 'date_fin', 'actif']]]);
    }

    public function test_admin_peut_creer_un_taux_exonere(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/referentiels/tva-taux', $this->payload(['type' => 'exonere', 'taux' => 0, 'designation' => 'Exonere']))
            ->assertCreated()
            ->assertJsonPath('data.type', 'exonere');

        $this->assertDatabaseHas('tva_taux', ['type' => 'exonere', 'taux' => 0]);
    }

    public function test_admin_peut_modifier_un_taux(): void
    {
        $taux = TvaTaux::create($this->payload());

        $this->actingAs($this->admin)
            ->putJson("/api/v1/referentiels/tva-taux/{$taux->id}", $this->payload(['designation' => 'TVA maj', 'date_fin' => '2026-12-31']))
            ->assertOk()
            ->assertJsonPath('data.designation', 'TVA maj')
            ->assertJsonPath('data.date_fin', '2026-12-31');
    }

    public function test_admin_peut_supprimer_un_taux_non_utilise(): void
    {
        $taux = TvaTaux::create($this->payload());

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/referentiels/tva-taux/{$taux->id}")
            ->assertOk();

        $this->assertDatabaseMissing('tva_taux', ['id' => $taux->id]);
    }

    public function test_suppression_bloquee_si_factures_liees(): void
    {
        $taux = TvaTaux::create($this->payload());
        Facture::factory()->create(['tva_taux_id' => $taux->id]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/referentiels/tva-taux/{$taux->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('tva_taux', ['id' => $taux->id]);
    }

    public function test_validation_type_reduit_refuse(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/referentiels/tva-taux', $this->payload(['type' => 'reduit']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_validation_date_fin_avant_debut_refusee(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/referentiels/tva-taux', $this->payload(['date_debut' => '2024-06-01', 'date_fin' => '2024-01-01']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_fin']);
    }

    public function test_validation_taux_hors_bornes_refusee(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/referentiels/tva-taux', $this->payload(['taux' => 150]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['taux']);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/referentiels/tva-taux', $this->payload(['taux' => -5]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['taux']);
    }

    public function test_non_admin_ne_peut_pas_gerer_les_taux(): void
    {
        $this->actingAs($this->secretaire)
            ->postJson('/api/v1/referentiels/tva-taux', $this->payload())
            ->assertStatus(403);
    }

    public function test_creer_un_taux_cloture_le_precedent_du_meme_type(): void
    {
        $ancien = TvaTaux::create($this->payload(['taux' => 19, 'date_debut' => '2023-01-01', 'date_fin' => null]));
        $exonere = TvaTaux::create($this->payload(['type' => 'exonere', 'taux' => 0, 'designation' => 'Exonere', 'date_debut' => '2023-01-01']));

        $this->actingAs($this->admin)
            ->postJson('/api/v1/referentiels/tva-taux', $this->payload(['taux' => 21, 'designation' => 'TVA 21', 'date_debut' => '2026-06-18']))
            ->assertCreated();

        // L'ancien standard est cloture la veille du nouveau ; l'exonere (autre type) n'est pas touche
        $this->assertEquals('2026-06-17', $ancien->fresh()->date_fin?->toDateString());
        $this->assertNull($exonere->fresh()->date_fin);

        // Resolution historique : 21% au 18/06, 19% au 01/06
        $this->assertEquals(21.0, (float) TvaTaux::enVigueurLe('2026-06-18')->taux);
        $this->assertEquals(19.0, (float) TvaTaux::enVigueurLe('2026-06-01')->taux);
    }
}
