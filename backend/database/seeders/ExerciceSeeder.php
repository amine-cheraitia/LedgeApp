<?php

namespace Database\Seeders;

use App\Models\Exercice;
use Illuminate\Database\Seeder;

class ExerciceSeeder extends Seeder
{
    public function run(): void
    {
        Exercice::firstOrCreate(
            ['annee' => 2026],
            [
                'date_ouverture' => '2026-01-01',
                'date_cloture' => '2026-12-31',
                'statut' => 'ouvert',
            ]
        );
    }
}
