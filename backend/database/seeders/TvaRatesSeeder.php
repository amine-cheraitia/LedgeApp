<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TvaRatesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tva_rates')->updateOrInsert(
            ['taux' => 19.00, 'type' => 'standard'],
            [
                'taux' => 19.00,
                'designation' => 'TVA standard LF 2023, art. 21',
                'date_debut' => '2023-01-01',
                'date_fin' => null,
                'type' => 'standard',
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('tva_rates')->updateOrInsert(
            ['taux' => 9.00, 'type' => 'reduit'],
            [
                'taux' => 9.00,
                'designation' => 'TVA réduite LF 2023, art. 23',
                'date_debut' => '2023-01-01',
                'date_fin' => null,
                'type' => 'reduit',
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('timbre_rates')->updateOrInsert(
            ['taux' => 1.00],
            [
                'taux' => 1.00,
                'plafond' => 2500.00,
                'designation' => 'Timbre fiscal LF 2024 (1%, plafonné 2 500 DA)',
                'date_debut' => '2024-01-01',
                'date_fin' => null,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
