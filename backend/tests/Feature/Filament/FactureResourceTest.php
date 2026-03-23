<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\FactureResource;
use App\Filament\Resources\FactureResource\Pages\CreateFacture;
use App\Filament\Resources\FactureResource\Pages\EditFacture;
use App\Filament\Resources\FactureResource\Pages\ListFactures;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\Setting;
use App\Models\TimbreRate;
use App\Models\TvaRate;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FactureResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $collaborateur;
    protected TvaRate $tvaRate;
    protected TimbreRate $timbreRate;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        \Spatie\Permission\Models\Role::create(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'collaborateur', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'secretaire', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'client', 'guard_name' => 'web']);

        // Create users
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->collaborateur = User::factory()->create();
        $this->collaborateur->assignRole('collaborateur');

        // Create exercice
        Exercice::create([
            'annee'          => 2026,
            'statut'         => 'ouvert',
            'date_ouverture' => '2026-01-01',
        ]);

        // Create TvaRate
        $this->tvaRate = TvaRate::create([
            'taux'        => 19,
            'designation' => 'TVA 19%',
            'type'        => 'standard',
            'date_debut'  => '2020-01-01',
            'date_fin'    => null,
            'actif'       => true,
        ]);

        // Create TimbreRate
        $this->timbreRate = TimbreRate::create([
            'taux'        => 1,
            'plafond'     => 2500,
            'designation' => 'Timbre fiscal 1%',
            'date_debut'  => '2020-01-01',
            'date_fin'    => null,
            'actif'       => true,
        ]);

        // Create Settings
        Setting::create(['key' => 'devis_prefixe', 'value' => 'DV', 'group' => 'facturation']);
        Setting::create(['key' => 'facture_prefixe', 'value' => 'FF', 'group' => 'facturation']);

        // Set up Filament panel
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        Filament::bootCurrentPanel();
    }

    public function test_admin_peut_acceder_a_la_liste_des_factures(): void
    {
        $this->actingAs($this->admin)
            ->get(route('filament.admin.resources.factures.index'))
            ->assertSuccessful();
    }

    public function test_collaborateur_peut_acceder_a_la_liste_des_factures(): void
    {
        $this->actingAs($this->collaborateur)
            ->get(route('filament.admin.resources.factures.index'))
            ->assertSuccessful();
    }

    public function test_collaborateur_ne_peut_pas_acceder_au_formulaire_de_creation(): void
    {
        $this->actingAs($this->collaborateur)
            ->get(route('filament.admin.resources.factures.create'))
            ->assertForbidden();
    }

    public function test_la_table_affiche_les_factures(): void
    {
        $exercice = Exercice::current();
        $facture = Facture::factory()->create([
            'exercice_id'    => $exercice->id,
            'created_by'     => $this->admin->id,
            'tva_rate_id'    => $this->tvaRate->id,
            'timbre_rate_id' => $this->timbreRate->id,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(ListFactures::class)
            ->assertCanSeeTableRecords([$facture]);
    }

    public function test_la_table_peut_filtrer_par_statut_paiement(): void
    {
        $exercice = Exercice::current();

        $enAttente = Facture::factory()->create([
            'exercice_id'    => $exercice->id,
            'created_by'     => $this->admin->id,
            'tva_rate_id'    => $this->tvaRate->id,
            'timbre_rate_id' => $this->timbreRate->id,
            'statut_paiement' => 'en_attente',
        ]);

        $solde = Facture::factory()->create([
            'exercice_id'    => $exercice->id,
            'created_by'     => $this->admin->id,
            'tva_rate_id'    => $this->tvaRate->id,
            'timbre_rate_id' => $this->timbreRate->id,
            'statut_paiement' => 'solde',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(ListFactures::class)
            ->filterTable('statut_paiement', 'en_attente')
            ->assertCanSeeTableRecords([$enAttente])
            ->assertCanNotSeeTableRecords([$solde]);
    }

    public function test_admin_peut_creer_une_facture(): void
    {
        $entreprise = Entreprise::factory()->create();

        $this->actingAs($this->admin);

        Livewire::test(CreateFacture::class)
            ->fillForm([
                'type'            => 'FF',
                'entreprise_id'   => $entreprise->id,
                'date_facture'    => '2026-03-16',
                'statut_paiement' => 'en_attente',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('factures', [
            'entreprise_id'   => $entreprise->id,
            'statut_paiement' => 'en_attente',
            'type'            => 'FF',
        ]);
    }

    public function test_admin_peut_modifier_une_facture(): void
    {
        $exercice = Exercice::current();
        $facture = Facture::factory()->create([
            'exercice_id'    => $exercice->id,
            'created_by'     => $this->admin->id,
            'tva_rate_id'    => $this->tvaRate->id,
            'timbre_rate_id' => $this->timbreRate->id,
            'statut_paiement' => 'en_attente',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(EditFacture::class, ['record' => $facture->getRouteKey()])
            ->fillForm([
                'statut_paiement' => 'partiel',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('factures', [
            'id'              => $facture->id,
            'statut_paiement' => 'partiel',
        ]);
    }

    public function test_la_table_peut_filtrer_par_type(): void
    {
        $exercice = Exercice::current();

        $facture = Facture::factory()->create([
            'exercice_id'    => $exercice->id,
            'created_by'     => $this->admin->id,
            'tva_rate_id'    => $this->tvaRate->id,
            'timbre_rate_id' => $this->timbreRate->id,
            'type'           => 'FF',
        ]);

        $avoir = Facture::factory()->avoir()->create([
            'exercice_id'    => $exercice->id,
            'created_by'     => $this->admin->id,
            'tva_rate_id'    => $this->tvaRate->id,
            'timbre_rate_id' => $this->timbreRate->id,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(ListFactures::class)
            ->filterTable('type', 'FF')
            ->assertCanSeeTableRecords([$facture])
            ->assertCanNotSeeTableRecords([$avoir]);
    }
}
