<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

    public function test_creating_an_entreprise_is_logged_with_the_causer(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/entreprises', [
                'raison_sociale' => 'Audit SARL',
                'regime_fiscal' => 'forfait',
                'categorie' => 'TPE',
                'statut' => 'prospect',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Entreprise::class,
            'event' => 'created',
            'causer_id' => $this->admin->id,
        ]);
    }

    public function test_updating_an_entreprise_logs_the_changed_fields(): void
    {
        $entreprise = Entreprise::factory()->create(['raison_sociale' => 'Avant']);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/entreprises/{$entreprise->id}", ['raison_sociale' => 'Apres'])
            ->assertOk();

        $activity = Activity::where('subject_type', Entreprise::class)
            ->where('event', 'updated')
            ->latest()
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('Apres', $activity->properties['attributes']['raison_sociale']);
        $this->assertSame('Avant', $activity->properties['old']['raison_sociale']);
    }

    public function test_user_password_is_never_logged(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/users', [
                'name' => 'Nouveau Staff',
                'email' => 'staff@example.com',
                'password' => 'Password123!',
                'role' => 'collaborateur',
            ])
            ->assertCreated();

        $activity = Activity::where('subject_type', User::class)
            ->where('event', 'created')
            ->latest()
            ->first();

        $this->assertNotNull($activity);
        $this->assertArrayNotHasKey('password', $activity->properties['attributes'] ?? []);
        $this->assertArrayNotHasKey('remember_token', $activity->properties['attributes'] ?? []);
    }

    public function test_admin_can_list_the_audit_log(): void
    {
        Entreprise::factory()->create();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'event', 'description', 'subject_type', 'changes', 'created_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_secretaire_cannot_access_the_audit_log(): void
    {
        $this->actingAs($this->secretaire)
            ->getJson('/api/v1/audit-logs')
            ->assertForbidden();
    }

    public function test_the_audit_log_can_be_filtered_by_event(): void
    {
        $entreprise = Entreprise::factory()->create(['raison_sociale' => 'Filtre SARL']);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/entreprises/{$entreprise->id}", ['raison_sociale' => 'Filtre SARL v2'])
            ->assertOk();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-logs?event=updated')
            ->assertOk();

        $events = collect($response->json('data'))->pluck('event')->unique();
        $this->assertEquals(['updated'], $events->values()->all());
    }
}
