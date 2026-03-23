<?php

namespace Tests\Feature\Filament\Portail;

use App\Filament\Portail\Resources\FactureResource;
use App\Filament\Portail\Resources\FactureResource\Pages\ListFactures;
use App\Filament\Portail\Resources\FactureResource\Pages\ViewFacture;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\TimbreRate;
use App\Models\TvaRate;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PortailFactureResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;
    protected Entreprise $entreprise;
    protected TvaRate $tvaRate;
    protected TimbreRate $timbreRate;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::create(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'collaborateur', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'secretaire', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'client', 'guard_name' => 'web']);

        $this->entreprise = Entreprise::factory()->client()->create();

        $this->client = User::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'portail_actif' => true,
        ]);
        $this->client->assignRole('client');

        Exercice::factory()->ouvert()->create(['annee' => 2026]);

        $this->tvaRate = TvaRate::create([
            'taux'        => 19,
            'designation' => 'TVA 19%',
            'type'        => 'standard',
            'date_debut'  => '2020-01-01',
            'date_fin'    => null,
            'actif'       => true,
        ]);

        $this->timbreRate = TimbreRate::create([
            'taux'        => 1,
            'plafond'     => 2500,
            'designation' => 'Timbre fiscal 1%',
            'date_debut'  => '2020-01-01',
            'date_fin'    => null,
            'actif'       => true,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('portail'));
    }

    public function test_client_peut_voir_la_liste_de_ses_factures(): void
    {
        $facture = Facture::factory()->create([
            'entreprise_id'   => $this->entreprise->id,
            'tva_rate_id'     => $this->tvaRate->id,
            'timbre_rate_id'  => $this->timbreRate->id,
        ]);

        Livewire::actingAs($this->client)
            ->test(ListFactures::class)
            ->assertCanSeeTableRecords([$facture]);
    }

    public function test_client_ne_voit_pas_les_factures_des_autres_entreprises(): void
    {
        $autreEntreprise = Entreprise::factory()->client()->create();
        $factureAutre = Facture::factory()->create([
            'entreprise_id'  => $autreEntreprise->id,
            'tva_rate_id'    => $this->tvaRate->id,
            'timbre_rate_id' => $this->timbreRate->id,
        ]);

        Livewire::actingAs($this->client)
            ->test(ListFactures::class)
            ->assertCanNotSeeTableRecords([$factureAutre]);
    }

    public function test_client_peut_voir_le_detail_dune_facture(): void
    {
        $facture = Facture::factory()->create([
            'entreprise_id'   => $this->entreprise->id,
            'tva_rate_id'     => $this->tvaRate->id,
            'timbre_rate_id'  => $this->timbreRate->id,
        ]);

        Livewire::actingAs($this->client)
            ->test(ViewFacture::class, ['record' => $facture->id])
            ->assertSuccessful();
    }

    public function test_client_ne_peut_pas_creer_une_facture(): void
    {
        $this->assertFalse(FactureResource::canCreate());
    }

    public function test_client_ne_peut_pas_modifier_une_facture(): void
    {
        $facture = Facture::factory()->create([
            'entreprise_id'   => $this->entreprise->id,
            'tva_rate_id'     => $this->tvaRate->id,
            'timbre_rate_id'  => $this->timbreRate->id,
        ]);

        $this->actingAs($this->client);
        $this->assertFalse(FactureResource::canEdit($facture));
    }

    public function test_client_ne_peut_pas_supprimer_une_facture(): void
    {
        $facture = Facture::factory()->create([
            'entreprise_id'   => $this->entreprise->id,
            'tva_rate_id'     => $this->tvaRate->id,
            'timbre_rate_id'  => $this->timbreRate->id,
        ]);

        $this->actingAs($this->client);
        $this->assertFalse(FactureResource::canDelete($facture));
    }

    public function test_user_sans_portail_actif_ne_peut_pas_acceder_au_portail(): void
    {
        $userInactif = User::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'portail_actif' => false,
        ]);
        $userInactif->assignRole('client');

        $panel = Filament::getPanel('portail');
        $this->assertFalse($userInactif->canAccessPanel($panel));
    }
}
