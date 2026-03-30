<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PortailService
{
    /**
     * Active l'acces portail pour une entreprise cliente.
     *
     * @return array{user: User, temporary_password: string}
     */
    public function activerPortail(Entreprise $entreprise, string $name, string $email): array
    {
        if ($entreprise->statut !== 'client') {
            throw ValidationException::withMessages([
                'entreprise' => ['Seule une entreprise avec le statut client peut acceder au portail.'],
            ]);
        }

        if ($this->getClientUser($entreprise)) {
            throw new ConflictHttpException('Un acces portail existe deja pour cette entreprise.');
        }

        $tempPassword = Str::random(12);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($tempPassword),
            'entreprise_id' => $entreprise->id,
            'portail_actif' => true,
        ]);

        $user->assignRole('client');

        return [
            'user' => $user->load('roles'),
            'temporary_password' => $tempPassword,
        ];
    }

    /**
     * Bascule l'etat portail_actif pour le user client de l'entreprise.
     */
    public function togglePortail(Entreprise $entreprise): User
    {
        $user = $this->getClientUser($entreprise);

        if (! $user) {
            abort(404, 'Aucun acces portail trouve.');
        }

        $user->update(['portail_actif' => ! $user->portail_actif]);

        return $user;
    }

    /**
     * Retourne le user client lie a l'entreprise, ou null.
     */
    private function getClientUser(Entreprise $entreprise): ?User
    {
        return $entreprise->users()
            ->whereHas('roles', fn ($q) => $q->where('name', 'client'))
            ->first();
    }
}
