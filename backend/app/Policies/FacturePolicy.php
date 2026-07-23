<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Facture;
use App\Models\User;

class FacturePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire', 'collaborateur']);
    }

    public function view(User $user, Facture $facture): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire', 'collaborateur']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Facture $facture): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Facture $facture): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Transmission de la facture au client par mail.
     * Autorise a la secretaire : elle transmet les factures sans pouvoir les creer/supprimer.
     */
    public function transmettre(User $user, Facture $facture): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }
}
