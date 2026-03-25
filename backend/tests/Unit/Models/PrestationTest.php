<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\CategorieEntreprise;
use App\Models\Prestation;
use App\Models\RegimeFiscal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrestationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_prix_ht_correctly(): void
    {
        RegimeFiscal::create(['code' => 'reel', 'designation' => 'Reel', 'indice' => 1.5]);
        CategorieEntreprise::create(['code' => 'PME', 'designation' => 'PME', 'indice' => 1.75]);

        $prestation = Prestation::create([
            'code' => 'ACMPT',
            'designation' => 'Assistance comptable',
            'tarif_initial' => 120000,
            'duree_mois' => 12,
            'actif' => true,
        ]);

        // 120000 * 1.5 * 1.75 = 315000
        $prixHt = $prestation->calculerPrixHt('reel', 'PME');
        $this->assertEquals(315000, $prixHt);
    }
}
