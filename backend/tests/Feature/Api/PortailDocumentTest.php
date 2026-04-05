<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortailDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Entreprise $entreprise;

    private Exercice $exercice;

    private Prestation $prestation;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);

        $this->entreprise = Entreprise::factory()->create(['statut' => 'client']);
        $this->exercice = Exercice::factory()->create(['statut' => 'ouvert', 'annee' => 2026]);
        $this->prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            ['designation' => 'Accompagnement comptable', 'tarif_initial' => 120000, 'duree_mois' => 12]
        );

        $this->client = User::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'portail_actif' => true,
        ]);
        $this->client->assignRole('client');
    }

    private function creerMission(array $overrides = []): Mission
    {
        return Mission::factory()->create(array_merge([
            'entreprise_id' => $this->entreprise->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
        ], $overrides));
    }

    public function test_client_voit_documents_visibles(): void
    {
        $this->creerMission([
            'convention_numero' => 'CV2026-001',
            'visible_portail' => true,
        ]);

        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/portail/documents');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_client_ne_voit_pas_documents_non_visibles(): void
    {
        $this->creerMission([
            'convention_numero' => 'CV2026-001',
            'visible_portail' => false,
        ]);

        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/portail/documents');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_client_ne_voit_pas_documents_sans_numero(): void
    {
        // visible_portail = true mais aucun numero genere
        $this->creerMission([
            'convention_numero' => null,
            'mandat_numero' => null,
            'visible_portail' => true,
        ]);

        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/portail/documents');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_client_ne_voit_pas_documents_autre_entreprise(): void
    {
        $autreEntreprise = Entreprise::factory()->create(['statut' => 'client']);
        Mission::factory()->create([
            'entreprise_id' => $autreEntreprise->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
            'convention_numero' => 'CV2026-002',
            'visible_portail' => true,
        ]);

        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/portail/documents');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_telecharger_convention_pdf(): void
    {
        Setting::set('cabinet_nom', 'Cabinet Test');

        $mission = $this->creerMission([
            'convention_numero' => 'CV2026-001',
            'visible_portail' => true,
        ]);

        $response = $this->actingAs($this->client)
            ->get("/api/v1/portail/documents/{$mission->id}/convention/pdf");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_telecharger_convention_pdf_mission_non_visible_interdit(): void
    {
        $mission = $this->creerMission([
            'convention_numero' => 'CV2026-001',
            'visible_portail' => false,
        ]);

        $response = $this->actingAs($this->client)
            ->get("/api/v1/portail/documents/{$mission->id}/convention/pdf");

        $response->assertForbidden();
    }

    public function test_telecharger_convention_pdf_autre_entreprise_interdit(): void
    {
        $autreEntreprise = Entreprise::factory()->create(['statut' => 'client']);
        $mission = Mission::factory()->create([
            'entreprise_id' => $autreEntreprise->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
            'convention_numero' => 'CV2026-003',
            'visible_portail' => true,
        ]);

        $response = $this->actingAs($this->client)
            ->get("/api/v1/portail/documents/{$mission->id}/convention/pdf");

        $response->assertForbidden();
    }

    public function test_telecharger_mandat_pdf(): void
    {
        Setting::set('cabinet_nom', 'Cabinet Test');

        $mission = $this->creerMission([
            'mandat_numero' => 'MD2026-001',
            'visible_portail' => true,
        ]);

        $response = $this->actingAs($this->client)
            ->get("/api/v1/portail/documents/{$mission->id}/mandat/pdf");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_staff_ne_peut_pas_acceder_portail_documents(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/portail/documents');

        $response->assertForbidden();
    }
}
