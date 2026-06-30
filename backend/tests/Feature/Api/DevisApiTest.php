<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\DevisMail;
use App\Models\CategorieEntreprise;
use App\Models\Contact;
use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Prestation;
use App\Models\RegimeFiscal;
use App\Models\Setting;
use App\Models\TvaTaux;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DevisApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Entreprise $entreprise;

    private Prestation $prestation;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'collaborateur']);
        Role::create(['name' => 'secretaire']);
        Role::create(['name' => 'client']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        // Referentiel tarifaire
        RegimeFiscal::create(['code' => 'reel', 'designation' => 'Régime Réel', 'indice' => 1.50]);
        CategorieEntreprise::create(['code' => 'PME', 'designation' => 'PME', 'indice' => 1.75]);

        $this->entreprise = Entreprise::factory()->create([
            'regime_fiscal' => 'reel',
            'categorie' => 'PME',
        ]);

        // ACMPT : 120 000 x 1.5 (reel) x 1.75 (PME) = 315 000 DA
        $this->prestation = Prestation::create([
            'code' => 'ACMPT',
            'designation' => 'Assistance Comptable',
            'tarif_initial' => 120000,
            'duree_mois' => 12,
            'actif' => true,
        ]);

        Exercice::create([
            'annee' => (int) date('Y'),
            'date_ouverture' => date('Y').'-01-01',
            'date_cloture' => date('Y').'-12-31',
            'statut' => 'ouvert',
        ]);

        TvaTaux::create([
            'taux' => 19,
            'designation' => 'TVA standard',
            'date_debut' => '2023-01-01',
            'type' => 'standard',
            'actif' => true,
        ]);

        Setting::set('devis_prefixe', 'DV');

        Mail::fake();
    }

    public function test_can_create_devis_avec_une_prestation(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/devis', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_devis' => '2026-03-31',
                'date_validite' => '2026-04-30',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.statut', 'brouillon')
            ->assertJsonPath('data.prestation_id', $this->prestation->id);

        $data = $response->json('data');
        // Prix HT = 120 000 x 1.5 x 1.75 = 315 000 DA
        $this->assertEquals(315000, (float) $data['prix_ht']);
        $this->assertEquals(315000, (float) $data['montant_ht']);
        $this->assertStringStartsWith('DV', $data['numero']);
    }

    public function test_prix_ht_calcule_via_grille_tarifaire(): void
    {
        // Regime forfait (x1.0) + TPE (x1.0) => prix HT = tarif_initial
        RegimeFiscal::create(['code' => 'forfait', 'designation' => 'Régime Forfait', 'indice' => 1.00]);
        CategorieEntreprise::create(['code' => 'TPE', 'designation' => 'TPE', 'indice' => 1.00]);

        $entrepriseTpe = Entreprise::factory()->create([
            'regime_fiscal' => 'forfait',
            'categorie' => 'TPE',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/devis', [
                'entreprise_id' => $entrepriseTpe->id,
                'prestation_id' => $this->prestation->id,
                'date_devis' => '2026-03-31',
                'date_validite' => '2026-04-30',
            ]);

        $response->assertCreated();
        // Prix HT = 120 000 x 1.0 x 1.0 = 120 000 DA
        $this->assertEquals(120000, (float) $response->json('data.prix_ht'));
    }

    public function test_tva_calcule_sur_prix_ht(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/devis', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_devis' => '2026-03-31',
                'date_validite' => '2026-04-30',
            ]);

        $response->assertCreated();
        $data = $response->json('data');

        // HT = 315 000
        $this->assertEquals(315000, (float) $data['montant_ht']);
        // Taux snapshot persiste sur le devis (parite avec la facture)
        $this->assertEquals(19, (float) $data['taux_tva']);
        // TVA = 315 000 * 19% = 59 850
        $this->assertEquals(59850, (float) $data['montant_tva']);
        // TTC = 315 000 + 59 850 = 374 850
        $this->assertEquals(374850, (float) $data['montant_ttc']);

        $this->assertDatabaseHas('devis', ['id' => $data['id'], 'taux_tva' => 19]);
    }

    public function test_creation_devis_sans_prestation_echoue(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/devis', [
                'entreprise_id' => $this->entreprise->id,
                'date_devis' => '2026-03-31',
                'date_validite' => '2026-04-30',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['prestation_id']);
    }

    public function test_can_list_devis(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/devis', [
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'date_devis' => '2026-03-31',
            'date_validite' => '2026-04-30',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/devis');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_devis_exonere_a_une_tva_nulle(): void
    {
        TvaTaux::create([
            'taux' => 0,
            'designation' => 'TVA exoneree',
            'date_debut' => '2023-01-01',
            'type' => 'exonere',
            'actif' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/devis', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_devis' => '2026-03-31',
                'date_validite' => '2026-04-30',
                'type_tva' => 'exonere',
            ]);

        $response->assertCreated();
        $data = $response->json('data');
        // Exonere : taux et TVA = 0, TTC = HT
        $this->assertEquals(315000, (float) $data['montant_ht']);
        $this->assertEquals(0, (float) $data['taux_tva']);
        $this->assertEquals(0, (float) $data['montant_tva']);
        $this->assertEquals(315000, (float) $data['montant_ttc']);
    }

    public function test_devis_standard_sans_taux_en_vigueur_refuse(): void
    {
        // Le seul taux standard demarre le 2023-01-01 : un devis date avant n'a aucun taux applicable.
        // On doit refuser (409) au lieu d'appliquer silencieusement 0%.
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/devis', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_devis' => '2022-12-31',
                'date_validite' => '2023-01-31',
            ]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('devis', 0);
    }

    public function test_modifier_devis_recalcule_les_totaux(): void
    {
        $devisId = $this->actingAs($this->admin)->postJson('/api/v1/devis', [
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'date_devis' => '2026-03-31',
            'date_validite' => '2026-04-30',
        ])->json('data.id');

        // Nouvelle prestation : 100 000 x 1.5 (reel) x 1.75 (PME) = 262 500 DA
        $autrePrestation = Prestation::create([
            'code' => 'AUDIT',
            'designation' => 'Audit',
            'tarif_initial' => 100000,
            'duree_mois' => 12,
            'actif' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/devis/{$devisId}", ['prestation_id' => $autrePrestation->id]);

        $response->assertOk();
        $data = $response->json('data');
        // Totaux recalcules de facon coherente, taux snapshot conserve (19%)
        $this->assertEquals(262500, (float) $data['montant_ht']);
        $this->assertEquals(19, (float) $data['taux_tva']);
        $this->assertEquals(49875, (float) $data['montant_tva']); // 262 500 * 19%
        $this->assertEquals(312375, (float) $data['montant_ttc']);
    }

    public function test_cannot_delete_non_brouillon_devis(): void
    {
        $createResponse = $this->actingAs($this->admin)->postJson('/api/v1/devis', [
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'date_devis' => '2026-03-31',
            'date_validite' => '2026-04-30',
        ]);

        $devisId = $createResponse->json('data.id');

        $this->actingAs($this->admin)->postJson("/api/v1/devis/{$devisId}/envoyer");

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/devis/{$devisId}");

        $response->assertStatus(409);
    }

    public function test_workflow_envoyer_accepter_refuser(): void
    {
        $devisId = $this->actingAs($this->admin)->postJson('/api/v1/devis', [
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'date_devis' => '2026-03-31',
            'date_validite' => '2026-04-30',
        ])->json('data.id');

        // Envoyer
        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/envoyer")
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'envoye');

        // Ne peut pas accepter un devis non envoye (deja envoye ici, tester avec brouillon)
        $devisId2 = $this->actingAs($this->admin)->postJson('/api/v1/devis', [
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'date_devis' => '2026-03-31',
            'date_validite' => '2026-04-30',
        ])->json('data.id');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId2}/accepter")
            ->assertStatus(409);

        // Accepter le premier devis (envoye)
        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/accepter")
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'accepte');

        // Tester refuser sur un devis envoye
        $devisId3 = $this->actingAs($this->admin)->postJson('/api/v1/devis', [
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'date_devis' => '2026-03-31',
            'date_validite' => '2026-04-30',
        ])->json('data.id');

        $this->actingAs($this->admin)->postJson("/api/v1/devis/{$devisId3}/envoyer");
        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId3}/refuser")
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'refuse');
    }

    public function test_pdf_devis_retourne_un_pdf(): void
    {
        $devisId = $this->actingAs($this->admin)->postJson('/api/v1/devis', [
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'date_devis' => '2026-03-31',
            'date_validite' => '2026-04-30',
        ])->json('data.id');

        $response = $this->actingAs($this->admin)
            ->get("/api/v1/devis/{$devisId}/pdf");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_numerotation_sequentielle(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->admin)->postJson('/api/v1/devis', [
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
                'date_devis' => '2026-03-31',
                'date_validite' => '2026-04-30',
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/v1/devis');

        $numeros = collect($response->json('data'))->pluck('numero')->sort()->values();
        $annee = date('Y');
        $this->assertEquals("DV{$annee}-001", $numeros[0]);
        $this->assertEquals("DV{$annee}-002", $numeros[1]);
        $this->assertEquals("DV{$annee}-003", $numeros[2]);
    }

    public function test_envoyer_devis_envoie_un_mail_avec_pdf_et_passe_en_envoye(): void
    {
        $devisId = $this->actingAs($this->admin)->postJson('/api/v1/devis', [
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'date_devis' => '2026-03-31',
            'date_validite' => '2026-04-30',
        ])->json('data.id');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/envoyer")
            ->assertOk()
            ->assertJsonPath('data.statut', 'envoye');

        Mail::assertSent(DevisMail::class, fn (DevisMail $mail) => $mail->hasTo($this->entreprise->email));

        $devis = Devis::findOrFail($devisId);
        $attachments = (new DevisMail($devis))->attachments();
        $this->assertCount(1, $attachments);
        $this->assertSame("devis-{$devis->numero}.pdf", $attachments[0]->as);
    }

    public function test_envoyer_devis_priorise_le_contact_principal(): void
    {
        Contact::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Diallo',
            'email' => 'contact.principal@example.com',
            'est_principal' => true,
        ]);

        $devisId = $this->actingAs($this->admin)->postJson('/api/v1/devis', [
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'date_devis' => '2026-03-31',
            'date_validite' => '2026-04-30',
        ])->json('data.id');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/envoyer")
            ->assertOk();

        // Le contact principal prime sur l'email general de l'entreprise
        Mail::assertSent(DevisMail::class, fn (DevisMail $mail) => $mail->hasTo('contact.principal@example.com'));
    }

    public function test_envoyer_devis_echec_envoi_garde_brouillon(): void
    {
        $devisId = $this->actingAs($this->admin)->postJson('/api/v1/devis', [
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'date_devis' => '2026-03-31',
            'date_validite' => '2026-04-30',
        ])->json('data.id');

        // Panne d'envoi -> 409 propre (pas de 500) et le devis reste en brouillon
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/envoyer")
            ->assertStatus(409);

        $this->assertEquals('brouillon', Devis::findOrFail($devisId)->statut);
    }

    public function test_envoyer_devis_sans_email_entreprise_refuse(): void
    {
        $this->entreprise->update(['email' => null]);

        $devisId = $this->actingAs($this->admin)->postJson('/api/v1/devis', [
            'entreprise_id' => $this->entreprise->id,
            'prestation_id' => $this->prestation->id,
            'date_devis' => '2026-03-31',
            'date_validite' => '2026-04-30',
        ])->json('data.id');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/devis/{$devisId}/envoyer")
            ->assertStatus(409);

        $this->assertEquals('brouillon', Devis::findOrFail($devisId)->statut);
        Mail::assertNothingSent();
    }
}
