<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            AdminUserSeeder::class,
            SettingsSeeder::class,
            ExerciceSeeder::class,
            TvaTauxSeeder::class,
            PrestationsSeeder::class,
        ]);
    }
}
