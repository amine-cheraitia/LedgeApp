<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingServiceTest extends TestCase
{
    use RefreshDatabase;

    private SettingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SettingService::class);
    }

    public function test_met_a_jour_un_parametre_existant(): void
    {
        Setting::create([
            'key' => 'cabinet_nom',
            'value' => 'Ancien Nom',
            'group' => 'general',
            'label' => 'Nom du cabinet',
        ]);

        $this->service->mettreAJour([
            ['key' => 'cabinet_nom', 'value' => 'Nouveau Nom'],
        ]);

        $this->assertDatabaseHas('settings', ['key' => 'cabinet_nom', 'value' => 'Nouveau Nom']);
        $this->assertDatabaseCount('settings', 1);
    }

    public function test_cree_le_parametre_absent(): void
    {
        $this->service->mettreAJour([
            ['key' => 'facture_prefixe', 'value' => 'FF'],
        ]);

        $this->assertDatabaseHas('settings', ['key' => 'facture_prefixe', 'value' => 'FF']);
    }

    public function test_met_a_jour_plusieurs_parametres_en_un_appel(): void
    {
        Setting::create([
            'key' => 'cabinet_nom',
            'value' => 'Ancien',
            'group' => 'general',
            'label' => 'Nom du cabinet',
        ]);

        $this->service->mettreAJour([
            ['key' => 'cabinet_nom', 'value' => 'Cabinet Ledge'],
            ['key' => 'devis_prefixe', 'value' => 'DV'],
        ]);

        $this->assertDatabaseHas('settings', ['key' => 'cabinet_nom', 'value' => 'Cabinet Ledge']);
        $this->assertDatabaseHas('settings', ['key' => 'devis_prefixe', 'value' => 'DV']);
    }

    public function test_accepte_une_valeur_nulle(): void
    {
        Setting::create([
            'key' => 'cabinet_slogan',
            'value' => 'Ancien slogan',
            'group' => 'general',
            'label' => 'Slogan',
        ]);

        $this->service->mettreAJour([
            ['key' => 'cabinet_slogan', 'value' => null],
        ]);

        $this->assertDatabaseHas('settings', ['key' => 'cabinet_slogan', 'value' => null]);
    }
}
