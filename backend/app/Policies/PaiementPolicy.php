<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Paiement;
use App\Models\User;

class PaiementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    /**
     * L'admin peut supprimer n'importe quel paiement ; la secretaire uniquement
     * ses propres saisies (garde-fou sur le recouvrement).
     */
    public function delete(User $user, Paiement $paiement): bool
    {
        return $user->hasRole('admin') || $paiement->recorded_by === $user->id;
    }
}
