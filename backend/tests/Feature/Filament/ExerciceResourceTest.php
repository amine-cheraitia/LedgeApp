<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ExerciceResource\Pages\CreateExercice;
use App\Filament\Resources\ExerciceResource\Pages\EditExercice;
use App\Filament\Resources\ExerciceResource\Pages\ListExercices;
use App\Models\Exercice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExerciceResourceTest extends TestCase
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

        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        Filament::bootCurrentPanel();
    }

    // ---------------------------------------------------------------
    // Accès via HTTP
    // ---------------------------------------------------------------

    public function test_admin_peut_acceder_a_la_liste_des_exercices(): void
    {
        $this->actingAs($this->admin)
            ->get(route('filament.admin.resources.exercices.index'))
            ->assertSuccessful();
    }

    public function test_collaborateur_ne_peut_pas_acceder_a_la_liste_des_exercices(): void
    {
        $this->actingAs($this->collaborateur)
            ->get(route('filament.admin.resources.exercices.index'))
            ->assertForbidden();
    }

    public function test_admin_peut_acceder_au_formulaire_de_creation(): void
    {
        $this->actingAs($this->admin)
            ->get(route('filament.admin.resources.exercices.create'))
            ->assertSuccessful();
    }

    // ---------------------------------------------------------------
    // Table via Livewire
    // ---------------------------------------------------------------

    public function test_la_table_affiche_les_exercices(): void
    {
        $exercices = Exercice::factory()->count(3)->create();

        $this->actingAs($this->admin);

        Livewire::test(ListExercices::class)
            ->assertCanSeeTableRecords($exercices);
    }

    // ---------------------------------------------------------------
    // Création via Livewire
    // ---------------------------------------------------------------

    public function test_admin_peut_creer_un_exercice(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateExercice::class)
            ->fillForm([
                'annee'          => 2030,
                'statut'         => 'ouvert',
                'date_ouverture' => '2030-01-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('exercices', [
            'annee'  => 2030,
            'statut' => 'ouvert',
        ]);
    }

    public function test_annee_est_obligatoire(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateExercice::class)
            ->fillForm([
                'annee'          => null,
                'statut'         => 'ouvert',
                'date_ouverture' => '2030-01-01',
            ])
            ->call('create')
            ->assertHasFormErrors(['annee' => 'required']);
    }

    public function test_annee_doit_etre_unique(): void
    {
        Exercice::factory()->create(['annee' => 2025]);

        $this->actingAs($this->admin);

        Livewire::test(CreateExercice::class)
            ->fillForm([
                'annee'          => 2025,
                'statut'         => 'ouvert',
                'date_ouverture' => '2025-01-01',
            ])
            ->call('create')
            ->assertHasFormErrors(['annee' => 'unique']);
    }

    // ---------------------------------------------------------------
    // Modification via Livewire
    // ---------------------------------------------------------------

    public function test_admin_peut_modifier_un_exercice(): void
    {
        $exercice = Exercice::factory()->create([
            'annee'  => 2028,
            'statut' => 'ouvert',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(EditExercice::class, ['record' => $exercice->getRouteKey()])
            ->fillForm([
                'annee'          => 2028,
                'statut'         => 'cloture',
                'date_ouverture' => '2028-01-01',
                'date_cloture'   => '2028-12-31',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $exercice->refresh();
        $this->assertEquals('cloture', $exercice->statut);
    }

    // ---------------------------------------------------------------
    // Action clôturer
    // ---------------------------------------------------------------

    public function test_admin_peut_cloturer_un_exercice_ouvert(): void
    {
        $exercice = Exercice::factory()->ouvert()->create(['annee' => 2027]);

        $this->actingAs($this->admin);

        Livewire::test(ListExercices::class)
            ->callTableAction('cloturer', $exercice)
            ->assertHasNoActionErrors();

        $exercice->refresh();
        $this->assertEquals('cloture', $exercice->statut);
        $this->assertNotNull($exercice->date_cloture);
    }

    public function test_action_cloturer_invisible_pour_exercice_deja_cloture(): void
    {
        $exercice = Exercice::factory()->cloture()->create(['annee' => 2024]);

        $this->actingAs($this->admin);

        Livewire::test(ListExercices::class)
            ->assertTableActionHidden('cloturer', $exercice);
    }
}
