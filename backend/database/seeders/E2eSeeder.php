<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Comptes de test dedies au harnais E2E Playwright (frontend/e2e/).
 *
 * N'est JAMAIS appele par DatabaseSeeder : uniquement via
 * `php artisan db:seed --class=E2eSeeder --env=e2e` (voir frontend/e2e/global-setup.ts).
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $secretaire = User::firstOrCreate(
            ['email' => 'secretaire.e2e@ledge.dz'],
            [
                'name' => 'Secretaire E2E',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $secretaire->assignRole('secretaire');

        $collaborateur = User::firstOrCreate(
            ['email' => 'collaborateur.e2e@ledge.dz'],
            [
                'name' => 'Collaborateur E2E',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $collaborateur->assignRole('collaborateur');
    }
}
