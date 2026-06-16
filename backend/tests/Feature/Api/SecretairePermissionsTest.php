<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\Mission;
use App\Models\Paiement;
use App\Models\Prestation;
use App\Models\TvaTaux;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Perimetre du role secretaire :
 *  - gere le CRUD des entreprises (sans suppression) + leurs contacts ;
 *  - gere les creances / recouvrement (consulter, relancer, enregistrer les paiements) ;
 *  - peut envoyer un devis et transmettre une facture (lecture + PDF) ;
 *  - ne cree / ne supprime PAS devis, factures, avoirs.
 */
class SecretairePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $secretaire;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'secretaire']);

        $this->secretaire = User::factory()->create();
        $this->secretaire->assignRole('secretaire');

        $this->exercice = Exercice::firstOrCreate(
            ['annee' => 2026],
            ['date_ouverture' => '2026-01-01', 'statut' => 'ouvert']
        );
    }

    /** @return array<string, mixed> */
    private function payloadEntreprise(array $overrides = []): array
    {
        return array_merge([
            'raison_sociale' => 'Entreprise Test SARL',
            'regime_fiscal' => 'reel',
            'categorie' => 'PME',
            'statut' => 'prospect',
        ], $overrides);
    }

    private function creerEntreprise(): Entreprise
    {
        return Entreprise::factory()->create(['statut' => 'client']);
    }

    private function creerDevisBrouillon(): Devis
    {
        $prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            ['designation' => 'Comptabilite', 'tarif_initial' => 120000]
        );

        return Devis::create([
            'entreprise_id' => $this->creerEntreprise()->id,
            'prestation_id' => $prestation->id,
            'exercice_id' => $this->exercice->id,
            'created_by' => $this->secretaire->id,
            'numero' => 'DV2026-001',
            'date_devis' => now()->toDateString(),
            'date_validite' => now()->addDays(30)->toDateString(),
            'prix_ht' => 250000,
            'montant_ht' => 250000,
            'montant_tva' => 47500,
            'montant_timbre' => 0,
            'montant_ttc' => 297500,
            'statut' => 'brouillon',
        ]);
    }

    private function creerFacture(): Facture
    {
        $tva = TvaTaux::firstOrCreate(
            ['taux' => 19],
            ['designation' => 'TVA 19%', 'date_debut' => '2020-01-01', 'actif' => true]
        );
        $entreprise = $this->creerEntreprise();
        $prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            ['designation' => 'Comptabilite', 'tarif_initial' => 120000]
        );
        $mission = Mission::factory()->create([
            'entreprise_id' => $entreprise->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $prestation->id,
            'prix_ht' => 100000,
        ]);

        return Facture::factory()->create([
            'type' => 'FF',
            'entreprise_id' => $entreprise->id,
            'exercice_id' => $this->exercice->id,
            'mission_id' => $mission->id,
            'tva_taux_id' => $tva->id,
            'montant_ht' => 100000,
            'taux_tva' => 19,
            'montant_tva' => 19000,
            'montant_ttc' => 119000,
            'montant_paye' => 0,
            'statut_paiement' => 'en_attente',
            'date_facture' => now()->startOfMonth()->toDateString(),
            'date_echeance' => now()->subDays(20)->toDateString(),
        ]);
    }

    private function creerMission(): Mission
    {
        $prestation = Prestation::firstOrCreate(
            ['code' => 'ACMPT'],
            ['designation' => 'Comptabilite', 'tarif_initial' => 120000]
        );

        return Mission::factory()->create([
            'entreprise_id' => $this->creerEntreprise()->id,
            'exercice_id' => $this->exercice->id,
            'prestation_id' => $prestation->id,
            'prix_ht' => 100000,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Autorise : entreprises (CRU) + contacts
    // ─────────────────────────────────────────────────────────────────────

    public function test_secretaire_peut_lister_les_entreprises(): void
    {
        $this->actingAs($this->secretaire)
            ->getJson('/api/v1/entreprises')
            ->assertOk();
    }

    public function test_secretaire_peut_creer_une_entreprise(): void
    {
        $this->actingAs($this->secretaire)
            ->postJson('/api/v1/entreprises', $this->payloadEntreprise())
            ->assertSuccessful();
    }

    public function test_secretaire_peut_modifier_une_entreprise(): void
    {
        $entreprise = $this->creerEntreprise();

        $this->actingAs($this->secretaire)
            ->putJson("/api/v1/entreprises/{$entreprise->id}", $this->payloadEntreprise(['statut' => 'client']))
            ->assertSuccessful();
    }

    public function test_secretaire_ne_peut_pas_supprimer_une_entreprise(): void
    {
        $entreprise = $this->creerEntreprise();

        $this->actingAs($this->secretaire)
            ->deleteJson("/api/v1/entreprises/{$entreprise->id}")
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Autorise : lecture facturation + envoi devis + recouvrement
    // ─────────────────────────────────────────────────────────────────────

    public function test_secretaire_peut_consulter_devis_et_factures(): void
    {
        $this->actingAs($this->secretaire)->getJson('/api/v1/devis')->assertOk();
        $this->actingAs($this->secretaire)->getJson('/api/v1/factures')->assertOk();
        $this->actingAs($this->secretaire)->getJson('/api/v1/creances')->assertOk();
    }

    public function test_secretaire_peut_envoyer_un_devis_au_client(): void
    {
        $devis = $this->creerDevisBrouillon();

        $this->actingAs($this->secretaire)
            ->postJson("/api/v1/devis/{$devis->id}/envoyer")
            ->assertOk();

        $this->assertSame('envoye', $devis->fresh()->statut);
    }

    public function test_secretaire_peut_enregistrer_un_paiement(): void
    {
        $facture = $this->creerFacture();

        $this->actingAs($this->secretaire)
            ->postJson("/api/v1/factures/{$facture->id}/paiements", [
                'montant' => 50000,
                'date_paiement' => now()->toDateString(),
                'mode_paiement' => 'virement',
            ])
            ->assertSuccessful();
    }

    public function test_secretaire_peut_envoyer_une_relance(): void
    {
        Mail::fake();
        $facture = $this->creerFacture();

        // La secretaire passe l'autorisation (le middleware role ne la bloque pas).
        $response = $this->actingAs($this->secretaire)
            ->postJson("/api/v1/factures/{$facture->id}/relances", ['niveau' => 1]);

        $this->assertNotSame(403, $response->getStatusCode());
    }

    // ─────────────────────────────────────────────────────────────────────
    // Interdit : production facturation (admin uniquement)
    // ─────────────────────────────────────────────────────────────────────

    public function test_secretaire_ne_peut_pas_creer_de_devis(): void
    {
        $this->actingAs($this->secretaire)
            ->postJson('/api/v1/devis', [])
            ->assertForbidden();
    }

    public function test_secretaire_ne_peut_pas_gerer_le_cycle_de_vie_du_devis(): void
    {
        $devis = $this->creerDevisBrouillon();

        $this->actingAs($this->secretaire)->deleteJson("/api/v1/devis/{$devis->id}")->assertForbidden();
        $this->actingAs($this->secretaire)->postJson("/api/v1/devis/{$devis->id}/accepter")->assertForbidden();
        $this->actingAs($this->secretaire)->postJson("/api/v1/devis/{$devis->id}/refuser")->assertForbidden();
        $this->actingAs($this->secretaire)->postJson("/api/v1/devis/{$devis->id}/convertir-en-mission")->assertForbidden();
    }

    public function test_secretaire_ne_peut_pas_creer_ni_supprimer_de_facture(): void
    {
        $facture = $this->creerFacture();

        $this->actingAs($this->secretaire)->postJson('/api/v1/factures', [])->assertForbidden();
        $this->actingAs($this->secretaire)->deleteJson("/api/v1/factures/{$facture->id}")->assertForbidden();
    }

    public function test_secretaire_ne_peut_pas_emettre_un_avoir(): void
    {
        $facture = $this->creerFacture();

        $this->actingAs($this->secretaire)
            ->postJson("/api/v1/factures/{$facture->id}/avoirs", [])
            ->assertForbidden();
    }

    public function test_secretaire_ne_peut_pas_supprimer_un_paiement(): void
    {
        $facture = $this->creerFacture();
        $paiement = Paiement::create([
            'facture_id' => $facture->id,
            'recorded_by' => $this->secretaire->id,
            'montant' => 10000,
            'date_paiement' => now()->toDateString(),
            'mode_paiement' => 'virement',
        ]);

        $this->actingAs($this->secretaire)
            ->deleteJson("/api/v1/factures/{$facture->id}/paiements/{$paiement->id}")
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Interdit : missions / planning (hors perimetre secretaire)
    // ─────────────────────────────────────────────────────────────────────

    public function test_secretaire_ne_peut_pas_acceder_aux_missions(): void
    {
        $mission = $this->creerMission();

        $this->actingAs($this->secretaire)->getJson('/api/v1/missions')->assertForbidden();
        $this->actingAs($this->secretaire)->postJson('/api/v1/missions', [])->assertForbidden();
        $this->actingAs($this->secretaire)->getJson("/api/v1/missions/{$mission->id}")->assertForbidden();
        $this->actingAs($this->secretaire)->getJson("/api/v1/missions/{$mission->id}/taches")->assertForbidden();
    }

    public function test_secretaire_ne_peut_pas_acceder_au_planning(): void
    {
        $this->actingAs($this->secretaire)->getJson('/api/v1/calendar')->assertForbidden();
        $this->actingAs($this->secretaire)->getJson('/api/v1/collaborateur/stats')->assertForbidden();
    }
}
