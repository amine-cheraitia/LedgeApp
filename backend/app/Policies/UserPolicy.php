<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Lister les utilisateurs : tout le personnel (pour les selects d'assignation).
     * Le controller restreint le contenu selon le role (annuaire complet pour l'admin,
     * personnel minimal pour les autres). Les clients sont bloques en amont (middleware backoffice).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'collaborateur', 'secretaire']);
    }

    /**
     * Consulter la fiche complete d'un utilisateur : gestion, reserve a l'admin.
     */
    public function view(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasRole('admin') && $user->id !== $target->id;
    }
}
