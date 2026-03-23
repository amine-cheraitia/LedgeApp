<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsPageTest extends TestCase
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

    public function test_admin_peut_acceder_a_la_page_parametres(): void
    {
        $this->actingAs($this->admin)
            ->get(route('filament.admin.pages.settings'))
            ->assertSuccessful();
    }

    public function test_collaborateur_ne_peut_pas_acceder_a_la_page_parametres(): void
    {
        $this->actingAs($this->collaborateur)
            ->get(route('filament.admin.pages.settings'))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Chargement des valeurs existantes
    // ---------------------------------------------------------------

    public function test_le_formulaire_charge_les_parametres_existants(): void
    {
        Setting::create(['key' => 'cabinet_nom',     'value' => 'Cabinet Test']);
        Setting::create(['key' => 'facture_prefixe', 'value' => 'FF']);
        Setting::create(['key' => 'devis_prefixe',   'value' => 'DV']);
        Setting::create(['key' => 'devise',          'value' => 'DA']);
        Setting::create(['key' => 'relance_niveau1_jours', 'value' => '7']);
        Setting::create(['key' => 'relance_niveau2_jours', 'value' => '15']);
        Setting::create(['key' => 'relance_niveau3_jours', 'value' => '30']);

        $this->actingAs($this->admin);

        Livewire::test(Settings::class)
            ->assertFormSet([
                'cabinet_nom'     => 'Cabinet Test',
                'facture_prefixe' => 'FF',
                'devise'          => 'DA',
            ]);
    }

    // ---------------------------------------------------------------
    // Sauvegarde via Livewire
    // ---------------------------------------------------------------

    public function test_admin_peut_sauvegarder_les_parametres(): void
    {
        Setting::create(['key' => 'cabinet_nom',          'value' => 'Ancien Nom']);
        Setting::create(['key' => 'cabinet_adresse',      'value' => '']);
        Setting::create(['key' => 'cabinet_nif',          'value' => '']);
        Setting::create(['key' => 'cabinet_nis',          'value' => '']);
        Setting::create(['key' => 'cabinet_rib',          'value' => '']);
        Setting::create(['key' => 'cabinet_telephone',    'value' => '']);
        Setting::create(['key' => 'facture_prefixe',      'value' => 'FF']);
        Setting::create(['key' => 'devis_prefixe',        'value' => 'DV']);
        Setting::create(['key' => 'devise',               'value' => 'DA']);
        Setting::create(['key' => 'relance_niveau1_jours','value' => '7']);
        Setting::create(['key' => 'relance_niveau2_jours','value' => '15']);
        Setting::create(['key' => 'relance_niveau3_jours','value' => '30']);

        $this->actingAs($this->admin);

        Livewire::test(Settings::class)
            ->fillForm([
                'cabinet_nom'          => 'Nouveau Cabinet',
                'facture_prefixe'      => 'FA',
                'devis_prefixe'        => 'DV',
                'devise'               => 'DA',
                'relance_niveau1_jours'=> 10,
                'relance_niveau2_jours'=> 20,
                'relance_niveau3_jours'=> 30,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('settings', ['key' => 'cabinet_nom', 'value' => 'Nouveau Cabinet']);
        $this->assertDatabaseHas('settings', ['key' => 'facture_prefixe', 'value' => 'FA']);
    }

    public function test_le_nom_du_cabinet_est_obligatoire(): void
    {
        Setting::create(['key' => 'cabinet_nom',          'value' => 'Cabinet']);
        Setting::create(['key' => 'facture_prefixe',      'value' => 'FF']);
        Setting::create(['key' => 'devis_prefixe',        'value' => 'DV']);
        Setting::create(['key' => 'devise',               'value' => 'DA']);
        Setting::create(['key' => 'relance_niveau1_jours','value' => '7']);
        Setting::create(['key' => 'relance_niveau2_jours','value' => '15']);
        Setting::create(['key' => 'relance_niveau3_jours','value' => '30']);

        $this->actingAs($this->admin);

        Livewire::test(Settings::class)
            ->fillForm(['cabinet_nom' => ''])
            ->call('save')
            ->assertHasFormErrors(['cabinet_nom' => 'required']);
    }

    public function test_le_prefixe_facture_est_obligatoire(): void
    {
        Setting::create(['key' => 'cabinet_nom',          'value' => 'Cabinet']);
        Setting::create(['key' => 'facture_prefixe',      'value' => 'FF']);
        Setting::create(['key' => 'devis_prefixe',        'value' => 'DV']);
        Setting::create(['key' => 'devise',               'value' => 'DA']);
        Setting::create(['key' => 'relance_niveau1_jours','value' => '7']);
        Setting::create(['key' => 'relance_niveau2_jours','value' => '15']);
        Setting::create(['key' => 'relance_niveau3_jours','value' => '30']);

        $this->actingAs($this->admin);

        Livewire::test(Settings::class)
            ->fillForm(['facture_prefixe' => ''])
            ->call('save')
            ->assertHasFormErrors(['facture_prefixe' => 'required']);
    }
}
