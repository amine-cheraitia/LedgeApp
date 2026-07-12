<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Provisioning des utilisateurs staff (admin/collaborateur/secretaire).
 * Aucun mot de passe n'est genere ni transmis : l'utilisateur le definit lui-meme
 * via le lien d'invitation. La creation d'un client passe par PortailService.
 */
class UserService
{
    public function __construct(private readonly InvitationService $invitationService) {}

    /**
     * Annuaire des utilisateurs. En mode non complet (non-admin), on ne renvoie
     * que le personnel (jamais les clients), pour les selects d'assignation.
     */
    public function listerAnnuaire(bool $complet, ?string $search, ?string $role, int $perPage): LengthAwarePaginator
    {
        $query = User::with('roles')
            // OR groupe explicitement : la restriction de role (plus bas) doit
            // s'appliquer a tout le groupe de recherche, jamais au seul email.
            ->when($search, fn ($q, $s) => $q->where(
                fn ($sub) => $sub->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")
            ))
            ->when($role, fn ($q, $r) => $q->role($r))
            ->latest();

        if (! $complet) {
            $query->role(['admin', 'collaborateur', 'secretaire']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Cree un utilisateur staff avec un mot de passe placeholder inutilisable et
     * envoie l'invitation de definition de mot de passe.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: User, invitation_url: string}
     */
    public function creerStaff(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::random(40)),
            'entreprise_id' => $data['entreprise_id'] ?? null,
        ]);

        $user->assignRole($data['role']);

        return [
            'user' => $user->load('roles'),
            'invitation_url' => $this->invitationService->inviter($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function mettreAJour(User $user, array $data): User
    {
        $user->update(collect($data)->except('role')->toArray());

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $user->load('roles');
    }

    public function renvoyerInvitation(User $user): string
    {
        return $this->invitationService->inviter($user);
    }
}
