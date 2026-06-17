<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Devis;
use App\Models\User;

class DevisPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire', 'collaborateur']);
    }

    public function view(User $user, Devis $devis): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire', 'collaborateur']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Devis $devis): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Devis $devis): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Envoi du devis au client (statut brouillon -> envoye).
     * Autorise a la secretaire : elle transmet les devis sans pouvoir les creer/modifier.
     */
    public function envoyer(User $user, Devis $devis): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }
}
