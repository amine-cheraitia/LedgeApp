<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TvaTauxSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tva_taux')->updateOrInsert(
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

        DB::table('tva_taux')->updateOrInsert(
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
    }
}
