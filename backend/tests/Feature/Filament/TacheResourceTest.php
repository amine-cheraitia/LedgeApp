<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TacheResource;
use App\Filament\Resources\TacheResource\Pages\CreateTache;
use App\Filament\Resources\TacheResource\Pages\EditTache;
use App\Filament\Resources\TacheResource\Pages\ListTaches;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\Tache;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TacheResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $collaborateur;
    protected Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::create(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'collaborateur', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'secretaire', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'client', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->collaborateur = User::factory()->create();
        $this->collaborateur->assignRole('collaborateur');

        $exercice = Exercice::factory()->ouvert()->create(['annee' => 2026]);

        $entreprise = Entreprise::factory()->client()->create();

        $prestation = Prestation::create([
            'code'          => 'CAC',
            'designation'   => 'Commissariat aux comptes',
            'tarif_initial' => 120000,
            'duree_mois'    => 12,
            'actif'         => true,
        ]);

        $this->mission = Mission::factory()->create([
            'entreprise_id' => $entreprise->id,
            'exercice_id'   => $exercice->id,
            'prestation_id' => $prestation->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_peut_voir_la_liste_des_taches(): void
    {
        $tache = Tache::factory()->create([
            'mission_id' => $this->mission->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListTaches::class)
            ->assertCanSeeTableRecords([$tache]);
    }

    public function test_admin_peut_creer_une_tache(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateTache::class)
            ->fillForm([
                'mission_id'    => $this->mission->id,
                'titre'         => 'Vérifier les comptes annuels',
                'statut'        => 'a_faire',
                'priorite'      => 3,
                'assigned_to'   => $this->collaborateur->id,
                'date_echeance' => '2026-06-30',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('taches', [
            'titre'       => 'Vérifier les comptes annuels',
            'assigned_to' => $this->collaborateur->id,
        ]);
    }

    public function test_collaborateur_peut_modifier_statut_tache(): void
    {
        $tache = Tache::factory()->assigneA($this->collaborateur)->create([
            'mission_id' => $this->mission->id,
        ]);

        Livewire::actingAs($this->collaborateur)
            ->test(EditTache::class, ['record' => $tache->id])
            ->fillForm([
                'statut' => 'en_cours',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('en_cours', $tache->fresh()->statut);
    }

    public function test_collaborateur_ne_voit_que_ses_taches(): void
    {
        $tacheAssignee = Tache::factory()->assigneA($this->collaborateur)->create([
            'mission_id' => $this->mission->id,
        ]);

        $tacheAutre = Tache::factory()->create([
            'mission_id' => $this->mission->id,
            'assigned_to' => $this->admin->id,
        ]);

        Livewire::actingAs($this->collaborateur)
            ->test(ListTaches::class)
            ->assertCanSeeTableRecords([$tacheAssignee])
            ->assertCanNotSeeTableRecords([$tacheAutre]);
    }

    public function test_collaborateur_ne_peut_pas_supprimer_tache(): void
    {
        $tache = Tache::factory()->create(['mission_id' => $this->mission->id]);

        $this->actingAs($this->collaborateur);
        $this->assertFalse(TacheResource::canDelete($tache));
    }

    public function test_admin_peut_ajouter_un_commentaire(): void
    {
        $tache = Tache::factory()->create(['mission_id' => $this->mission->id]);

        Livewire::actingAs($this->admin)
            ->test(EditTache::class, ['record' => $tache->id])
            ->fillForm([
                'commentaires' => [
                    [
                        'user_id'  => $this->admin->id,
                        'contenu'  => 'Première observation sur le dossier.',
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tache_commentaires', [
            'tache_id' => $tache->id,
            'contenu'  => 'Première observation sur le dossier.',
        ]);
    }
}
