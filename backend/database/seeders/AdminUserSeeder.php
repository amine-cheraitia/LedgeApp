<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@ledge.dz');
        $password = (string) env('ADMIN_PASSWORD', '');

        // En production, le mot de passe admin doit etre fourni explicitement via l'environnement.
        // En local / test, on retombe sur un mot de passe de developpement connu.
        if ($password === '') {
            if (app()->environment('production')) {
                throw new RuntimeException(
                    'ADMIN_PASSWORD est requis pour seeder l\'administrateur en production.'
                );
            }

            $password = 'password';
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrateur',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('admin');
    }
}
