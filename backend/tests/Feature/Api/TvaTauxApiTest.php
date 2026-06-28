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
        TvaTaux::create($this->payload(['date_debut' => '2023-01-01'])); // autre taux standard actif
        $taux = TvaTaux::create($this->payload(['date_debut' => '2024-01-01']));

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

    public function test_validation_date_fin_avant_debut_refusee_a_la_modification(): void
    {
        $taux = TvaTaux::create($this->payload());

        $this->actingAs($this->admin)
            ->putJson("/api/v1/referentiels/tva-taux/{$taux->id}", $this->payload(['date_debut' => '2024-06-01', 'date_fin' => '2024-01-01']))
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

    public function test_desactiver_le_dernier_taux_actif_du_type_refuse(): void
    {
        $taux = TvaTaux::create($this->payload(['date_debut' => '2024-01-01']));

        $this->actingAs($this->admin)
            ->putJson("/api/v1/referentiels/tva-taux/{$taux->id}", $this->payload(['actif' => false]))
            ->assertStatus(409);

        $this->assertTrue($taux->fresh()->actif);
    }

    public function test_supprimer_le_dernier_taux_actif_du_type_refuse(): void
    {
        $taux = TvaTaux::create($this->payload(['date_debut' => '2024-01-01']));

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/referentiels/tva-taux/{$taux->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('tva_taux', ['id' => $taux->id]);
    }

    public function test_desactivation_autorisee_si_un_autre_taux_actif_existe(): void
    {
        TvaTaux::create($this->payload(['date_debut' => '2023-01-01']));
        $nouveau = TvaTaux::create($this->payload(['date_debut' => '2024-01-01']));

        $this->actingAs($this->admin)
            ->putJson("/api/v1/referentiels/tva-taux/{$nouveau->id}", $this->payload(['actif' => false]))
            ->assertOk();

        $this->assertFalse($nouveau->fresh()->actif);
    }

    public function test_envigueurle_ignore_un_taux_inactif(): void
    {
        TvaTaux::create($this->payload(['taux' => 19, 'date_debut' => '2023-01-01', 'actif' => true]));
        TvaTaux::create($this->payload(['taux' => 25, 'date_debut' => '2026-01-01', 'actif' => false]));

        $taux = TvaTaux::enVigueurLe('2026-06-18', 'standard');

        $this->assertNotNull($taux);
        $this->assertEquals(19.0, (float) $taux->taux);
    }

    public function test_creer_un_taux_le_meme_jour_qu_un_actif_refuse(): void
    {
        TvaTaux::create($this->payload(['date_debut' => '2026-06-18']));

        $this->actingAs($this->admin)
            ->postJson('/api/v1/referentiels/tva-taux', $this->payload(['taux' => 21, 'date_debut' => '2026-06-18']))
            ->assertStatus(409);

        $this->assertDatabaseCount('tva_taux', 1);
    }

    public function test_modifier_la_date_debut_sur_le_jour_d_un_autre_actif_refuse(): void
    {
        TvaTaux::create($this->payload(['date_debut' => '2026-06-18']));
        $autre = TvaTaux::create($this->payload(['date_debut' => '2026-01-01']));

        $this->actingAs($this->admin)
            ->putJson("/api/v1/referentiels/tva-taux/{$autre->id}", $this->payload(['date_debut' => '2026-06-18']))
            ->assertStatus(409);

        $this->assertEquals('2026-01-01', $autre->fresh()->date_debut->toDateString());
    }

    public function test_modifier_la_date_debut_recloture_le_precedent(): void
    {
        $ancien = TvaTaux::create($this->payload(['date_debut' => '2023-01-01']));
        $courant = TvaTaux::create($this->payload(['taux' => 21, 'date_debut' => '2026-01-01']));

        // Deplacer le taux courant plus tard re-cloture l'ancien a la veille (re-versionnement a l'edition)
        $this->actingAs($this->admin)
            ->putJson("/api/v1/referentiels/tva-taux/{$courant->id}", $this->payload(['taux' => 21, 'date_debut' => '2026-06-18']))
            ->assertOk();

        $this->assertEquals('2026-06-17', $ancien->fresh()->date_fin?->toDateString());
    }
}
