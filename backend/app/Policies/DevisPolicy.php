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
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function update(User $user, Devis $devis): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function delete(User $user, Devis $devis): bool
    {
        return $user->hasRole('admin');
    }
}
