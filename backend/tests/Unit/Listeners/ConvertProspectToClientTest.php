<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\RegimeFiscal;
use App\Models\CategorieEntreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertProspectToClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_prospect_becomes_client_on_mission_creation(): void
    {
        $entreprise = Entreprise::factory()->create(['statut' => 'prospect']);

        Exercice::create([
            'annee' => (int) date('Y'),
            'date_ouverture' => date('Y') . '-01-01',
            'date_cloture' => date('Y') . '-12-31',
            'statut' => 'ouvert',
        ]);

        $prestation = Prestation::create([
            'code' => 'TEST',
            'designation' => 'Test',
            'tarif_initial' => 10000,
            'duree_mois' => 12,
            'actif' => true,
        ]);

        // La creation de la mission declenche l'observer -> event -> listener
        Mission::create([
            'entreprise_id' => $entreprise->id,
            'exercice_id' => Exercice::current()->id,
            'prestation_id' => $prestation->id,
            'reference' => 'M2026-001',
            'prix_ht' => 10000,
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31',
            'statut' => 'en_cours',
        ]);

        $entreprise->refresh();
        $this->assertEquals('client', $entreprise->statut);
    }

    public function test_already_client_stays_client(): void
    {
        $entreprise = Entreprise::factory()->create(['statut' => 'client']);

        Exercice::create([
            'annee' => (int) date('Y'),
            'date_ouverture' => date('Y') . '-01-01',
            'date_cloture' => date('Y') . '-12-31',
            'statut' => 'ouvert',
        ]);

        $prestation = Prestation::create([
            'code' => 'TEST',
            'designation' => 'Test',
            'tarif_initial' => 10000,
            'duree_mois' => 12,
            'actif' => true,
        ]);

        Mission::create([
            'entreprise_id' => $entreprise->id,
            'exercice_id' => Exercice::current()->id,
            'prestation_id' => $prestation->id,
            'reference' => 'M2026-002',
            'prix_ht' => 10000,
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31',
            'statut' => 'en_cours',
        ]);

        $entreprise->refresh();
        $this->assertEquals('client', $entreprise->statut);
    }
}
