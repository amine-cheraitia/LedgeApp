<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\DevisResource;
use App\Filament\Resources\DevisResource\Pages\CreateDevis;
use App\Filament\Resources\DevisResource\Pages\EditDevis;
use App\Filament\Resources\DevisResource\Pages\ListDevis;
use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Setting;
use App\Models\TimbreRate;
use App\Models\TvaRate;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DevisResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $collaborateur;

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
        TvaRate::create([
            'taux'       => 19,
            'designation' => 'TVA 19%',
            'type'       => 'standard',
            'date_debut' => '2020-01-01',
            'date_fin'   => null,
            'actif'      => true,
        ]);

        // Create TimbreRate
        TimbreRate::create([
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

    public function test_admin_peut_acceder_a_la_liste_des_devis(): void
    {
        $this->actingAs($this->admin)
            ->get(route('filament.admin.resources.devis.index'))
            ->assertSuccessful();
    }

    public function test_collaborateur_peut_acceder_a_la_liste_des_devis(): void
    {
        $this->actingAs($this->collaborateur)
            ->get(route('filament.admin.resources.devis.index'))
            ->assertSuccessful();
    }

    public function test_collaborateur_ne_peut_pas_acceder_au_formulaire_de_creation(): void
    {
        $this->actingAs($this->collaborateur)
            ->get(route('filament.admin.resources.devis.create'))
            ->assertForbidden();
    }

    public function test_la_table_affiche_les_devis(): void
    {
        $exercice = Exercice::current();
        $devis = Devis::factory()->create([
            'exercice_id' => $exercice->id,
            'created_by'  => $this->admin->id,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(ListDevis::class)
            ->assertCanSeeTableRecords([$devis]);
    }

    public function test_la_table_peut_filtrer_par_statut(): void
    {
        $exercice = Exercice::current();

        $brouillon = Devis::factory()->brouillon()->create([
            'exercice_id' => $exercice->id,
            'created_by'  => $this->admin->id,
        ]);

        $envoye = Devis::factory()->envoye()->create([
            'exercice_id' => $exercice->id,
            'created_by'  => $this->admin->id,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(ListDevis::class)
            ->filterTable('statut', 'brouillon')
            ->assertCanSeeTableRecords([$brouillon])
            ->assertCanNotSeeTableRecords([$envoye]);
    }

    public function test_admin_peut_creer_un_devis(): void
    {
        $entreprise = Entreprise::factory()->create();

        $this->actingAs($this->admin);

        Livewire::test(CreateDevis::class)
            ->fillForm([
                'entreprise_id' => $entreprise->id,
                'statut'        => 'brouillon',
                'date_devis'    => '2026-03-16',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('devis', [
            'entreprise_id' => $entreprise->id,
            'statut'        => 'brouillon',
        ]);
    }

    public function test_la_designation_est_obligatoire_dans_les_lignes(): void
    {
        $entreprise = Entreprise::factory()->create();

        $this->actingAs($this->admin);

        Livewire::test(CreateDevis::class)
            ->fillForm([
                'entreprise_id' => $entreprise->id,
                'statut'        => 'brouillon',
                'date_devis'    => '2026-03-16',
                'lignes'        => [
                    [
                        'designation'     => '',
                        'quantite'        => 1,
                        'prix_unitaire_ht' => 100000,
                        'total_ht'        => 100000,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['lignes.0.designation']);
    }

    public function test_admin_peut_modifier_un_devis(): void
    {
        $exercice = Exercice::current();
        $devis = Devis::factory()->brouillon()->create([
            'exercice_id' => $exercice->id,
            'created_by'  => $this->admin->id,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(EditDevis::class, ['record' => $devis->getRouteKey()])
            ->fillForm([
                'statut' => 'envoye',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('devis', [
            'id'     => $devis->id,
            'statut' => 'envoye',
        ]);
    }
}
