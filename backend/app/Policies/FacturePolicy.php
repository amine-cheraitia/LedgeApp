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
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function update(User $user, Facture $facture): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function delete(User $user, Facture $facture): bool
    {
        return $user->hasRole('admin');
    }
}
