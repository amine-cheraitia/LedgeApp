<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrestationsSeeder extends Seeder
{
    public function run(): void
    {
        $prestations = [
            ['code' => 'CAC',   'designation' => 'Audit Légal (Commissariat aux comptes)', 'tarif_initial' => 300000, 'duree_mois' => 36],
            ['code' => 'ACMPT', 'designation' => 'Assistance Comptable',                   'tarif_initial' => 120000, 'duree_mois' => 12],
            ['code' => 'AENT',  'designation' => "Accompagnement d'entreprise",             'tarif_initial' => 80000,  'duree_mois' => 12],
            ['code' => 'ASSC',  'designation' => 'Assainissement de la comptabilité',       'tarif_initial' => 100000, 'duree_mois' => 12],
            ['code' => 'A&C',   'designation' => 'Audit et conseil',                        'tarif_initial' => 110000, 'duree_mois' => 12],
        ];

        foreach ($prestations as $p) {
            DB::table('prestations')->updateOrInsert(
                ['code' => $p['code']],
                array_merge($p, ['actif' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        $regimes = [
            ['code' => 'forfait', 'designation' => 'Régime Forfait', 'indice' => 1.00],
            ['code' => 'reel',    'designation' => 'Régime Réel',    'indice' => 1.50],
        ];

        foreach ($regimes as $r) {
            DB::table('regimes_fiscaux')->updateOrInsert(
                ['code' => $r['code']],
                array_merge($r, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        $categories = [
            ['code' => 'TPE', 'designation' => 'Très Petite Entreprise', 'indice' => 1.00],
            ['code' => 'PME', 'designation' => 'Petite et Moyenne Entreprise', 'indice' => 1.75],
            ['code' => 'GE',  'designation' => 'Grande Entreprise', 'indice' => 2.00],
        ];

        foreach ($categories as $c) {
            DB::table('categories_entreprise')->updateOrInsert(
                ['code' => $c['code']],
                array_merge($c, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
