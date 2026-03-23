<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource; // kept for Livewire form tests
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $collaborateur;
    private User $secretaire;

    protected function setUp(): void
    {
        parent::setUp();

        // Vider le cache de permissions Spatie entre chaque test
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Créer les rôles
        Role::create(['name' => 'admin',         'guard_name' => 'web']);
        Role::create(['name' => 'collaborateur', 'guard_name' => 'web']);
        Role::create(['name' => 'secretaire',    'guard_name' => 'web']);
        Role::create(['name' => 'client',        'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->collaborateur = User::factory()->create();
        $this->collaborateur->assignRole('collaborateur');

        $this->secretaire = User::factory()->create();
        $this->secretaire->assignRole('secretaire');
    }

    // ---------------------------------------------------------------
    // Accès via HTTP (test des routes Filament)
    // ---------------------------------------------------------------

    public function test_admin_peut_acceder_a_la_liste_des_utilisateurs(): void
    {
        $this->actingAs($this->admin)
            ->get(route('filament.admin.resources.users.index'))
            ->assertSuccessful();
    }

    public function test_collaborateur_ne_peut_pas_acceder_a_la_liste_des_utilisateurs(): void
    {
        $this->actingAs($this->collaborateur)
            ->get(route('filament.admin.resources.users.index'))
            ->assertForbidden();
    }

    public function test_secretaire_ne_peut_pas_acceder_a_la_liste_des_utilisateurs(): void
    {
        $this->actingAs($this->secretaire)
            ->get(route('filament.admin.resources.users.index'))
            ->assertForbidden();
    }

    public function test_admin_peut_acceder_au_formulaire_de_creation(): void
    {
        $this->actingAs($this->admin)
            ->get(route('filament.admin.resources.users.create'))
            ->assertSuccessful();
    }

    public function test_collaborateur_ne_peut_pas_acceder_au_formulaire_de_creation(): void
    {
        $this->actingAs($this->collaborateur)
            ->get(route('filament.admin.resources.users.create'))
            ->assertForbidden();
    }

    public function test_admin_peut_acceder_au_formulaire_de_modification(): void
    {
        $this->actingAs($this->admin)
            ->get(route('filament.admin.resources.users.edit', ['record' => $this->collaborateur->getRouteKey()]))
            ->assertSuccessful();
    }

    // ---------------------------------------------------------------
    // Table via Livewire
    // ---------------------------------------------------------------

    public function test_la_table_affiche_les_utilisateurs_back_office(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords(
                User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'client'))->get()
            );
    }

    public function test_les_clients_sont_exclus_de_la_liste(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($this->admin);

        Livewire::test(ListUsers::class)
            ->assertCanNotSeeTableRecords(collect([$client]));
    }

    public function test_la_table_peut_filtrer_par_role(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListUsers::class)
            ->filterTable('roles', Role::where('name', 'collaborateur')->first()->id)
            ->assertCanSeeTableRecords(collect([$this->collaborateur]))
            ->assertCanNotSeeTableRecords(collect([$this->secretaire]));
    }

    public function test_la_table_peut_rechercher_par_nom(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListUsers::class)
            ->searchTable($this->collaborateur->name)
            ->assertCanSeeTableRecords(collect([$this->collaborateur]))
            ->assertCanNotSeeTableRecords(collect([$this->secretaire]));
    }

    // ---------------------------------------------------------------
    // Création via Livewire
    // ---------------------------------------------------------------

    public function test_admin_peut_creer_un_utilisateur_avec_un_role(): void
    {
        $this->actingAs($this->admin);

        $roleId = Role::where('name', 'collaborateur')->first()->id;

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name'     => 'Nouveau Collaborateur',
                'email'    => 'nouveau@ledge.dz',
                'password' => 'password123',
                'roles'    => $roleId,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'name'  => 'Nouveau Collaborateur',
            'email' => 'nouveau@ledge.dz',
        ]);

        $user = User::where('email', 'nouveau@ledge.dz')->first();
        $this->assertTrue($user->hasRole('collaborateur'));
    }

    public function test_la_creation_echoue_si_email_deja_utilise(): void
    {
        $this->actingAs($this->admin);

        $roleId = Role::where('name', 'collaborateur')->first()->id;

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name'     => 'Doublon',
                'email'    => $this->collaborateur->email,
                'password' => 'password123',
                'roles'    => $roleId,
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_le_mot_de_passe_est_obligatoire_a_la_creation(): void
    {
        $this->actingAs($this->admin);

        $roleId = Role::where('name', 'collaborateur')->first()->id;

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name'     => 'Sans Password',
                'email'    => 'sanspw@ledge.dz',
                'password' => '',
                'roles'    => $roleId,
            ])
            ->call('create')
            ->assertHasFormErrors(['password' => 'required']);
    }

    public function test_le_role_est_obligatoire_a_la_creation(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name'     => 'Sans Role',
                'email'    => 'sansrole@ledge.dz',
                'password' => 'password123',
                'roles'    => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['roles' => 'required']);
    }

    // ---------------------------------------------------------------
    // Modification via Livewire
    // ---------------------------------------------------------------

    public function test_admin_peut_modifier_le_nom_dun_utilisateur(): void
    {
        $this->actingAs($this->admin);

        $roleId = Role::where('name', 'secretaire')->first()->id;

        Livewire::test(EditUser::class, ['record' => $this->collaborateur->getRouteKey()])
            ->fillForm([
                'name'  => 'Collab Renommé',
                'email' => $this->collaborateur->email,
                'roles' => $roleId,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->collaborateur->refresh();
        $this->assertEquals('Collab Renommé', $this->collaborateur->name);
        $this->assertTrue($this->collaborateur->hasRole('secretaire'));
    }

    public function test_le_mot_de_passe_nest_pas_requis_en_modification(): void
    {
        $this->actingAs($this->admin);

        $roleId = Role::where('name', 'collaborateur')->first()->id;

        Livewire::test(EditUser::class, ['record' => $this->collaborateur->getRouteKey()])
            ->fillForm([
                'name'     => $this->collaborateur->name,
                'email'    => $this->collaborateur->email,
                'password' => '',
                'roles'    => $roleId,
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    // ---------------------------------------------------------------
    // Suppression via Livewire
    // ---------------------------------------------------------------

    public function test_admin_peut_supprimer_un_utilisateur(): void
    {
        $this->actingAs($this->admin);

        $userASupprimer = User::factory()->create();
        $userASupprimer->assignRole('collaborateur');

        Livewire::test(ListUsers::class)
            ->callTableAction('delete', $userASupprimer)
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('users', ['id' => $userASupprimer->id]);
    }
}
