<?php

namespace Tests\Feature\Filament\Portail;

use App\Filament\Portail\Resources\DevisResource;
use App\Filament\Portail\Resources\DevisResource\Pages\ListDevis;
use App\Filament\Portail\Resources\DevisResource\Pages\ViewDevis;
use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PortailDevisResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;
    protected Entreprise $entreprise;

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

        Filament::setCurrentPanel(Filament::getPanel('portail'));
    }

    public function test_client_peut_voir_la_liste_de_ses_devis(): void
    {
        $devis = Devis::factory()->create([
            'entreprise_id' => $this->entreprise->id,
        ]);

        Livewire::actingAs($this->client)
            ->test(ListDevis::class)
            ->assertCanSeeTableRecords([$devis]);
    }

    public function test_client_ne_voit_pas_les_devis_des_autres_entreprises(): void
    {
        $autreEntreprise = Entreprise::factory()->client()->create();
        $devisAutre = Devis::factory()->create([
            'entreprise_id' => $autreEntreprise->id,
        ]);

        Livewire::actingAs($this->client)
            ->test(ListDevis::class)
            ->assertCanNotSeeTableRecords([$devisAutre]);
    }

    public function test_client_peut_voir_le_detail_dun_devis(): void
    {
        $devis = Devis::factory()->create([
            'entreprise_id' => $this->entreprise->id,
        ]);

        Livewire::actingAs($this->client)
            ->test(ViewDevis::class, ['record' => $devis->id])
            ->assertSuccessful();
    }

    public function test_client_ne_peut_pas_creer_un_devis(): void
    {
        $this->assertFalse(DevisResource::canCreate());
    }

    public function test_client_ne_peut_pas_modifier_un_devis(): void
    {
        $devis = Devis::factory()->create([
            'entreprise_id' => $this->entreprise->id,
        ]);

        $this->actingAs($this->client);
        $this->assertFalse(DevisResource::canEdit($devis));
    }

    public function test_client_ne_peut_pas_supprimer_un_devis(): void
    {
        $devis = Devis::factory()->create([
            'entreprise_id' => $this->entreprise->id,
        ]);

        $this->actingAs($this->client);
        $this->assertFalse(DevisResource::canDelete($devis));
    }
}
