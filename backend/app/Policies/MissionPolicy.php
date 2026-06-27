<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Mission;
use App\Models\User;

class MissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'collaborateur']);
    }

    public function view(User $user, Mission $mission): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        // Accès accordé si le collaborateur est membre de la mission OU assigné à l'une de ses tâches
        return $mission->collaborateurs()->where('user_id', $user->id)->exists()
            || $mission->taches()->where('assigned_to', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Mission $mission): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Mission $mission): bool
    {
        return $user->hasRole('admin');
    }
}
