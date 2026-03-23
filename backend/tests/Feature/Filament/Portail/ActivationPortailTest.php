<?php

namespace Tests\Feature\Filament\Portail;

use App\Filament\Resources\EntrepriseResource\Pages\ListEntreprises;
use App\Models\Entreprise;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class ActivationPortailTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::create(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'collaborateur', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'secretaire', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'client', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_peut_activer_acces_portail_pour_une_entreprise_client(): void
    {
        Password::shouldReceive('sendResetLink')->once()->andReturn(Password::RESET_LINK_SENT);

        $entreprise = Entreprise::factory()->client()->create([
            'email'             => 'contact@entreprise.dz',
            'contact_principal' => 'Mohammed Benali',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListEntreprises::class)
            ->callTableAction('activer_portail', $entreprise);

        $userClient = User::where('entreprise_id', $entreprise->id)->first();

        $this->assertNotNull($userClient);
        $this->assertTrue($userClient->portail_actif);
        $this->assertTrue($userClient->hasRole('client'));
        $this->assertEquals($entreprise->email, $userClient->email);
    }

    public function test_activation_portail_non_visible_pour_prospect(): void
    {
        $prospect = Entreprise::factory()->prospect()->create();

        Livewire::actingAs($this->admin)
            ->test(ListEntreprises::class)
            ->assertTableActionHidden('activer_portail', $prospect);
    }

    public function test_activation_portail_non_visible_si_deja_actif(): void
    {
        $entreprise = Entreprise::factory()->client()->create();
        $client = User::factory()->create([
            'entreprise_id' => $entreprise->id,
            'portail_actif' => true,
        ]);
        $client->assignRole('client');

        Livewire::actingAs($this->admin)
            ->test(ListEntreprises::class)
            ->assertTableActionHidden('activer_portail', $entreprise);
    }

    public function test_admin_peut_revoquer_acces_portail(): void
    {
        $entreprise = Entreprise::factory()->client()->create();
        $client = User::factory()->create([
            'entreprise_id' => $entreprise->id,
            'portail_actif' => true,
        ]);
        $client->assignRole('client');

        Livewire::actingAs($this->admin)
            ->test(ListEntreprises::class)
            ->callTableAction('revoquer_portail', $entreprise);

        $this->assertFalse($client->fresh()->portail_actif);
    }

    public function test_revocation_portail_non_visible_sans_utilisateur_actif(): void
    {
        $entreprise = Entreprise::factory()->client()->create();

        Livewire::actingAs($this->admin)
            ->test(ListEntreprises::class)
            ->assertTableActionHidden('revoquer_portail', $entreprise);
    }

    public function test_collaborateur_ne_peut_pas_activer_portail(): void
    {
        $collaborateur = User::factory()->create();
        $collaborateur->assignRole('collaborateur');

        $entreprise = Entreprise::factory()->client()->create();

        Livewire::actingAs($collaborateur)
            ->test(ListEntreprises::class)
            ->assertTableActionDisabled('activer_portail', $entreprise);
    }
}
