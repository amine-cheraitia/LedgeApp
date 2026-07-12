<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\TvaTaux;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortailFactureTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Entreprise $entreprise;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);

        $this->entreprise = Entreprise::factory()->create(['statut' => 'client']);

        $this->client = User::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'portail_actif' => true,
        ]);
        $this->client->assignRole('client');
    }

    private function creerFacture(Entreprise $entreprise, array $overrides = []): Facture
    {
        $exercice = Exercice::firstOrCreate(
            ['annee' => 2026],
            ['statut' => 'ouvert', 'date_ouverture' => '2026-01-01']
        );
        $tva = TvaTaux::firstOrCreate(
            ['taux' => 19],
            ['designation' => 'TVA 19%', 'date_debut' => '2024-01-01', 'date_fin' => null]
        );
        $prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            ['designation' => 'Accompagnement comptable', 'tarif_initial' => 120000, 'duree_mois' => 12]
        );
        $mission = Mission::factory()->create([
            'entreprise_id' => $entreprise->id,
            'exercice_id' => $exercice->id,
            'prestation_id' => $prestation->id,
        ]);

        return Facture::factory()->create(array_merge([
            'entreprise_id' => $entreprise->id,
            'exercice_id' => $exercice->id,
            'mission_id' => $mission->id,
            'tva_taux_id' => $tva->id,
            'type' => 'FF',
            'statut_paiement' => 'en_attente',
        ], $overrides));
    }

    public function test_pdf_via_endpoint_facture_refuse_un_document_non_ff(): void
    {
        // L'endpoint facture ne sert que les factures (FF), pas les avoirs (FA) : 404.
        $avoirCommeFacture = $this->creerFacture($this->entreprise, ['type' => 'FA']);

        $this->actingAs($this->client)
            ->getJson("/api/v1/portail/factures/{$avoirCommeFacture->id}/pdf")
            ->assertNotFound();
    }

    public function test_client_voit_ses_factures(): void
    {
        $this->creerFacture($this->entreprise, ['numero' => 'FF2026-001']);
        $this->creerFacture($this->entreprise, ['numero' => 'FF2026-002']);

        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/portail/factures');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_client_ne_voit_pas_factures_autre_entreprise(): void
    {
        $autreEntreprise = Entreprise::factory()->create(['statut' => 'client']);
        $this->creerFacture($autreEntreprise, ['numero' => 'FF2026-999']);

        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/portail/factures');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_filtre_par_statut_paiement(): void
    {
        $this->creerFacture($this->entreprise, ['numero' => 'FF2026-001', 'statut_paiement' => 'en_attente']);
        $this->creerFacture($this->entreprise, ['numero' => 'FF2026-002', 'statut_paiement' => 'solde']);

        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/portail/factures?statut_paiement=solde');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_pdf_facture_appartenant_au_client(): void
    {
        $facture = $this->creerFacture($this->entreprise);

        $response = $this->actingAs($this->client)
            ->get("/api/v1/portail/factures/{$facture->id}/pdf");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_facture_autre_entreprise_interdit(): void
    {
        $autreEntreprise = Entreprise::factory()->create(['statut' => 'client']);
        $facture = $this->creerFacture($autreEntreprise, ['numero' => 'FF2026-999']);

        $response = $this->actingAs($this->client)
            ->getJson("/api/v1/portail/factures/{$facture->id}/pdf");

        $response->assertForbidden();
    }

    public function test_staff_ne_peut_pas_acceder_portail_factures(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/portail/factures');

        $response->assertForbidden();
    }
}
