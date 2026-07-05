<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\InvitationCompteMail;
use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Couverture ciblee des endpoints Entreprise : creation / modification /
 * suppression (protection), referentiels (wilayas, export CSV) et activation
 * du portail client (invitation via PortailService + InvitationService).
 */
class EntrepriseCoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $secretaire;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->secretaire = User::factory()->create();
        $this->secretaire->assignRole('secretaire');
    }

    private function payloadCreation(array $overrides = []): array
    {
        return array_merge([
            'raison_sociale' => 'Nouvelle SARL',
            'nif' => '1234567890',
            'nis' => '12345678901234',
            'regime_fiscal' => 'reel',
            'categorie' => 'PME',
            'statut' => 'prospect',
            'email' => 'contact@nouvelle.dz',
            'wilaya' => 'Alger',
        ], $overrides);
    }

    // ─── Creation (store) ───────────────────────────────────────────────────

    public function test_admin_peut_creer_entreprise_prospect(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/entreprises', $this->payloadCreation());

        $response->assertCreated()
            ->assertJsonPath('data.raison_sociale', 'Nouvelle SARL')
            ->assertJsonPath('data.statut', 'prospect');

        $this->assertDatabaseHas('entreprises', [
            'raison_sociale' => 'Nouvelle SARL',
            'nif' => '1234567890',
            'statut' => 'prospect',
        ]);
    }

    public function test_secretaire_peut_creer_entreprise(): void
    {
        $response = $this->actingAs($this->secretaire)
            ->postJson('/api/v1/entreprises', $this->payloadCreation([
                'raison_sociale' => 'Secretaire SARL',
                'nif' => '9999999999',
                'nis' => '99999999999999',
            ]));

        $response->assertCreated()
            ->assertJsonPath('data.raison_sociale', 'Secretaire SARL');
    }

    public function test_client_ne_peut_pas_creer_entreprise(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)
            ->postJson('/api/v1/entreprises', $this->payloadCreation())
            ->assertForbidden();
    }

    public function test_creation_echoue_sans_raison_sociale(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/entreprises', $this->payloadCreation(['raison_sociale' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('raison_sociale');
    }

    public function test_creation_echoue_sans_regime_fiscal(): void
    {
        $payload = $this->payloadCreation();
        unset($payload['regime_fiscal']);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/entreprises', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('regime_fiscal');
    }

    public function test_creation_echoue_sans_categorie(): void
    {
        $payload = $this->payloadCreation();
        unset($payload['categorie']);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/entreprises', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('categorie');
    }

    public function test_creation_echoue_statut_invalide(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/entreprises', $this->payloadCreation(['statut' => 'inexistant']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('statut');
    }

    public function test_creation_echoue_email_invalide(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/entreprises', $this->payloadCreation(['email' => 'pas-un-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    // ─── Modification (update) ───────────────────────────────────────────────

    public function test_admin_peut_modifier_entreprise(): void
    {
        $entreprise = Entreprise::factory()->create(['raison_sociale' => 'Avant']);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/entreprises/{$entreprise->id}", [
                'raison_sociale' => 'Apres',
                'wilaya' => 'Oran',
            ])
            ->assertOk()
            ->assertJsonPath('data.raison_sociale', 'Apres')
            ->assertJsonPath('data.wilaya', 'Oran');

        $this->assertDatabaseHas('entreprises', [
            'id' => $entreprise->id,
            'raison_sociale' => 'Apres',
        ]);
    }

    public function test_modification_bascule_statut_vers_client(): void
    {
        $entreprise = Entreprise::factory()->prospect()->create();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/entreprises/{$entreprise->id}", ['statut' => 'client'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'client');
    }

    public function test_modification_echoue_statut_invalide(): void
    {
        $entreprise = Entreprise::factory()->create();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/entreprises/{$entreprise->id}", ['statut' => 'zzz'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('statut');
    }

    // ─── Suppression (destroy + protection) ──────────────────────────────────

    public function test_admin_peut_supprimer_entreprise_sans_relation(): void
    {
        $entreprise = Entreprise::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/entreprises/{$entreprise->id}")
            ->assertOk();

        $this->assertSoftDeleted('entreprises', ['id' => $entreprise->id]);
    }

    public function test_suppression_bloquee_si_devis_lie(): void
    {
        $exercice = Exercice::create([
            'annee' => (int) date('Y'),
            'date_ouverture' => date('Y').'-01-01',
            'date_cloture' => date('Y').'-12-31',
            'statut' => 'ouvert',
        ]);

        $entreprise = Entreprise::factory()->create();

        Devis::create([
            'entreprise_id' => $entreprise->id,
            'exercice_id' => $exercice->id,
            'created_by' => $this->admin->id,
            'numero' => 'DV2026-001',
            'date_devis' => date('Y').'-01-01',
            'montant_ht' => 100000,
            'montant_tva' => 19000,
            'montant_ttc' => 119000,
            'statut' => 'brouillon',
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/entreprises/{$entreprise->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('entreprises', [
            'id' => $entreprise->id,
            'deleted_at' => null,
        ]);
    }

    public function test_secretaire_ne_peut_pas_supprimer_entreprise(): void
    {
        $entreprise = Entreprise::factory()->create();

        $this->actingAs($this->secretaire)
            ->deleteJson("/api/v1/entreprises/{$entreprise->id}")
            ->assertForbidden();
    }

    // ─── Referentiels (wilayas / export CSV) ─────────────────────────────────

    public function test_wilayas_retourne_liste_distincte_triee(): void
    {
        Entreprise::factory()->create(['wilaya' => 'Oran']);
        Entreprise::factory()->create(['wilaya' => 'Alger']);
        Entreprise::factory()->create(['wilaya' => 'Alger']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/entreprises/wilayas');

        $response->assertOk()
            ->assertJsonFragment(['data' => ['Alger', 'Oran']]);
    }

    public function test_export_csv_telecharge_un_fichier(): void
    {
        Entreprise::factory()->count(2)->create();

        $response = $this->actingAs($this->admin)
            ->get('/api/v1/entreprises/export-csv');

        $response->assertOk()
            ->assertDownload('entreprises.csv');
    }

    // ─── Portail client (activation / invitation / toggle) ───────────────────

    public function test_activer_portail_cree_user_client_et_envoie_invitation(): void
    {
        Mail::fake();
        $entreprise = Entreprise::factory()->client()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$entreprise->id}/activer-portail", [
                'name' => 'Client Portail',
                'email' => 'client.portail@exemple.dz',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'user', 'invitation_url']);

        $this->assertDatabaseHas('users', [
            'email' => 'client.portail@exemple.dz',
            'entreprise_id' => $entreprise->id,
            'portail_actif' => true,
        ]);

        $user = User::where('email', 'client.portail@exemple.dz')->first();
        $this->assertTrue($user->hasRole('client'));

        Mail::assertSent(InvitationCompteMail::class);
    }

    public function test_activer_portail_echoue_si_entreprise_non_client(): void
    {
        Mail::fake();
        $entreprise = Entreprise::factory()->prospect()->create();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$entreprise->id}/activer-portail", [
                'name' => 'Client Portail',
                'email' => 'refuse@exemple.dz',
            ])
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_activer_portail_echoue_si_acces_deja_existant(): void
    {
        Mail::fake();
        $entreprise = Entreprise::factory()->client()->create();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$entreprise->id}/activer-portail", [
                'name' => 'Premier',
                'email' => 'premier@exemple.dz',
            ])
            ->assertStatus(201);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$entreprise->id}/activer-portail", [
                'name' => 'Second',
                'email' => 'second@exemple.dz',
            ])
            ->assertStatus(409);
    }

    public function test_activer_portail_reserve_admin(): void
    {
        $entreprise = Entreprise::factory()->client()->create();

        $this->actingAs($this->secretaire)
            ->postJson("/api/v1/entreprises/{$entreprise->id}/activer-portail", [
                'name' => 'Client Portail',
                'email' => 'sec@exemple.dz',
            ])
            ->assertForbidden();
    }

    public function test_renvoyer_invitation_renvoie_un_lien(): void
    {
        Mail::fake();
        $entreprise = Entreprise::factory()->client()->create();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$entreprise->id}/activer-portail", [
                'name' => 'Client Portail',
                'email' => 'renvoi@exemple.dz',
            ])
            ->assertStatus(201);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$entreprise->id}/renvoyer-invitation")
            ->assertOk()
            ->assertJsonStructure(['message', 'invitation_url']);

        Mail::assertSent(InvitationCompteMail::class, 2);
    }

    public function test_toggle_portail_desactive_acces(): void
    {
        Mail::fake();
        $entreprise = Entreprise::factory()->client()->create();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$entreprise->id}/activer-portail", [
                'name' => 'Client Portail',
                'email' => 'toggle@exemple.dz',
            ])
            ->assertStatus(201);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/entreprises/{$entreprise->id}/toggle-portail")
            ->assertOk()
            ->assertJsonPath('portail_actif', false);

        $this->assertDatabaseHas('users', [
            'email' => 'toggle@exemple.dz',
            'portail_actif' => false,
        ]);
    }
}
