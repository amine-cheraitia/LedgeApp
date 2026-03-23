<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\EntrepriseResource\Pages\CreateEntreprise;
use App\Filament\Resources\EntrepriseResource\Pages\EditEntreprise;
use App\Filament\Resources\EntrepriseResource\Pages\ListEntreprises;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EntrepriseResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $collaborateur;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::create(['name' => 'admin',         'guard_name' => 'web']);
        Role::create(['name' => 'collaborateur', 'guard_name' => 'web']);
        Role::create(['name' => 'secretaire',    'guard_name' => 'web']);
        Role::create(['name' => 'client',        'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->collaborateur = User::factory()->create();
        $this->collaborateur->assignRole('collaborateur');
    }

    // ---------------------------------------------------------------
    // Accès via HTTP
    // ---------------------------------------------------------------

    public function test_admin_peut_acceder_a_la_liste_des_entreprises(): void
    {
        $this->actingAs($this->admin)
            ->get(route('filament.admin.resources.entreprises.index'))
            ->assertSuccessful();
    }

    public function test_collaborateur_peut_acceder_a_la_liste_des_entreprises(): void
    {
        // Collaborateur peut consulter les entreprises
        $this->actingAs($this->collaborateur)
            ->get(route('filament.admin.resources.entreprises.index'))
            ->assertSuccessful();
    }

    public function test_admin_peut_acceder_au_formulaire_de_creation(): void
    {
        $this->actingAs($this->admin)
            ->get(route('filament.admin.resources.entreprises.create'))
            ->assertSuccessful();
    }

    public function test_collaborateur_ne_peut_pas_acceder_au_formulaire_de_creation(): void
    {
        $this->actingAs($this->collaborateur)
            ->get(route('filament.admin.resources.entreprises.create'))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Table via Livewire
    // ---------------------------------------------------------------

    public function test_la_table_affiche_les_entreprises(): void
    {
        $entreprises = Entreprise::factory()->count(3)->create();

        $this->actingAs($this->admin);

        Livewire::test(ListEntreprises::class)
            ->assertCanSeeTableRecords($entreprises);
    }

    public function test_la_table_peut_filtrer_par_statut(): void
    {
        $client   = Entreprise::factory()->client()->create();
        $prospect = Entreprise::factory()->prospect()->create();

        $this->actingAs($this->admin);

        Livewire::test(ListEntreprises::class)
            ->filterTable('statut', 'client')
            ->assertCanSeeTableRecords(collect([$client]))
            ->assertCanNotSeeTableRecords(collect([$prospect]));
    }

    public function test_la_table_peut_rechercher_par_raison_sociale(): void
    {
        $entrepriseA = Entreprise::factory()->create(['raison_sociale' => 'SARL Alpha Industries']);
        $entrepriseB = Entreprise::factory()->create(['raison_sociale' => 'EURL Beta Services']);

        $this->actingAs($this->admin);

        Livewire::test(ListEntreprises::class)
            ->searchTable('Alpha')
            ->assertCanSeeTableRecords(collect([$entrepriseA]))
            ->assertCanNotSeeTableRecords(collect([$entrepriseB]));
    }

    // ---------------------------------------------------------------
    // Création via Livewire
    // ---------------------------------------------------------------

    public function test_admin_peut_creer_une_entreprise(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateEntreprise::class)
            ->fillForm([
                'raison_sociale' => 'SARL Test',
                'statut'         => 'prospect',
                'regime_fiscal'  => 'reel',
                'categorie'      => 'PME',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('entreprises', [
            'raison_sociale' => 'SARL Test',
            'statut'         => 'prospect',
        ]);
    }

    public function test_la_raison_sociale_est_obligatoire(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateEntreprise::class)
            ->fillForm([
                'raison_sociale' => '',
                'statut'         => 'prospect',
                'regime_fiscal'  => 'reel',
                'categorie'      => 'PME',
            ])
            ->call('create')
            ->assertHasFormErrors(['raison_sociale' => 'required']);
    }

    public function test_le_nif_doit_etre_unique(): void
    {
        $existante = Entreprise::factory()->create(['nif' => '0123456789']);

        $this->actingAs($this->admin);

        Livewire::test(CreateEntreprise::class)
            ->fillForm([
                'raison_sociale' => 'Nouvelle SARL',
                'nif'            => '0123456789',
                'statut'         => 'prospect',
                'regime_fiscal'  => 'reel',
                'categorie'      => 'PME',
            ])
            ->call('create')
            ->assertHasFormErrors(['nif' => 'unique']);
    }

    // ---------------------------------------------------------------
    // Modification via Livewire
    // ---------------------------------------------------------------

    public function test_admin_peut_modifier_une_entreprise(): void
    {
        $entreprise = Entreprise::factory()->create([
            'statut'    => 'prospect',
            'telephone' => '0555123456',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(EditEntreprise::class, ['record' => $entreprise->getRouteKey()])
            ->fillForm([
                'raison_sociale' => 'Nouveau Nom SARL',
                'statut'         => 'client',
                'regime_fiscal'  => $entreprise->regime_fiscal,
                'categorie'      => $entreprise->categorie,
                'telephone'      => '0555123456',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $entreprise->refresh();
        $this->assertEquals('Nouveau Nom SARL', $entreprise->raison_sociale);
        $this->assertEquals('client', $entreprise->statut);
    }

    public function test_le_nif_peut_rester_identique_a_la_modification(): void
    {
        $entreprise = Entreprise::factory()->create([
            'nif'       => '9876543210',
            'telephone' => '0555654321',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(EditEntreprise::class, ['record' => $entreprise->getRouteKey()])
            ->fillForm([
                'raison_sociale' => $entreprise->raison_sociale,
                'nif'            => '9876543210', // même NIF — ne doit pas déclencher l'erreur unique
                'statut'         => $entreprise->statut,
                'regime_fiscal'  => $entreprise->regime_fiscal,
                'categorie'      => $entreprise->categorie,
                'telephone'      => '0555654321',
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    // ---------------------------------------------------------------
    // Suppression (soft delete) via Livewire
    // ---------------------------------------------------------------

    public function test_admin_peut_supprimer_une_entreprise(): void
    {
        $entreprise = Entreprise::factory()->create();

        $this->actingAs($this->admin);

        Livewire::test(ListEntreprises::class)
            ->callTableAction('delete', $entreprise)
            ->assertHasNoActionErrors();

        $this->assertSoftDeleted('entreprises', ['id' => $entreprise->id]);
    }

    public function test_admin_peut_restaurer_une_entreprise_supprimee(): void
    {
        $entreprise = Entreprise::factory()->create();
        $entreprise->delete();

        $this->assertSoftDeleted('entreprises', ['id' => $entreprise->id]);

        $this->actingAs($this->admin);

        // La page d'édition expose l'action "restore" dans le header
        Livewire::test(EditEntreprise::class, ['record' => $entreprise->getRouteKey()])
            ->callAction('restore')
            ->assertHasNoActionErrors();

        $this->assertNotSoftDeleted('entreprises', ['id' => $entreprise->id]);
    }
}
