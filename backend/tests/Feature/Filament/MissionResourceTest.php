<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\MissionResource;
use App\Filament\Resources\MissionResource\Pages\CreateMission;
use App\Filament\Resources\MissionResource\Pages\EditMission;
use App\Filament\Resources\MissionResource\Pages\ListMissions;
use App\Models\CategorieEntreprise;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\RegimeFiscal;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MissionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $collaborateur;
    protected Entreprise $entreprise;
    protected Exercice $exercice;
    protected Prestation $prestation;

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

        $this->exercice = Exercice::factory()->ouvert()->create(['annee' => 2026]);

        $this->entreprise = Entreprise::factory()->client()->create([
            'regime_fiscal' => 'reel',
            'categorie'     => 'PME',
        ]);

        $this->prestation = Prestation::create([
            'code'          => 'CAC',
            'designation'   => 'Commissariat aux comptes',
            'tarif_initial' => 120000,
            'duree_mois'    => 12,
            'actif'         => true,
        ]);

        RegimeFiscal::create(['code' => 'reel', 'designation' => 'Réel', 'indice' => 1.5]);
        CategorieEntreprise::create(['code' => 'PME', 'designation' => 'PME', 'indice' => 1.75]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_peut_voir_la_liste_des_missions(): void
    {
        $mission = Mission::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'exercice_id'   => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListMissions::class)
            ->assertCanSeeTableRecords([$mission]);
    }

    public function test_admin_peut_creer_une_mission(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateMission::class)
            ->fillForm([
                'entreprise_id' => $this->entreprise->id,
                'exercice_id'   => $this->exercice->id,
                'prestation_id' => $this->prestation->id,
                'reference'     => 'M2026-001',
                'prix_ht'       => 315000,
                'date_debut'    => '2026-01-15',
                'date_fin'      => '2026-12-31',
                'statut'        => 'en_cours',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('missions', [
            'reference' => 'M2026-001',
            'prix_ht'   => 315000,
        ]);
    }

    public function test_admin_peut_modifier_une_mission(): void
    {
        $mission = Mission::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'exercice_id'   => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(EditMission::class, ['record' => $mission->id])
            ->fillForm([
                'statut' => 'terminee',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('terminee', $mission->fresh()->statut);
    }

    public function test_collaborateur_ne_voit_que_ses_missions(): void
    {
        $missionAssignee = Mission::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'exercice_id'   => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
        ]);
        $missionAssignee->collaborateurs()->attach($this->collaborateur->id);

        $missionAutre = Mission::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'exercice_id'   => $this->exercice->id,
            'prestation_id' => $this->prestation->id,
        ]);

        Livewire::actingAs($this->collaborateur)
            ->test(ListMissions::class)
            ->assertCanSeeTableRecords([$missionAssignee])
            ->assertCanNotSeeTableRecords([$missionAutre]);
    }

    public function test_collaborateur_ne_peut_pas_creer_de_mission(): void
    {
        $this->actingAs($this->collaborateur);
        $this->assertFalse(MissionResource::canCreate());
    }

    public function test_prix_ht_calcule_automatiquement(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateMission::class)
            ->fillForm([
                'entreprise_id' => $this->entreprise->id,
                'prestation_id' => $this->prestation->id,
            ])
            ->assertFormFieldExists('prix_ht');

        // 120000 * 1.5 (reel) * 1.75 (PME) = 315000
        $prixHt = $this->prestation->calculerPrixHt('reel', 'PME');
        $this->assertEquals(315000, $prixHt);
    }
}
