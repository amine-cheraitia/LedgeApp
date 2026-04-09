<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tache;
use App\Models\User;

class TachePolicy
{
    public function update(User $user, Tache $tache): bool
    {
        if ($user->hasAnyRole(['admin', 'secretaire'])) {
            return true;
        }

        return $user->hasRole('collaborateur') && $tache->assigned_to === $user->id;
    }

    public function delete(User $user, Tache $tache): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }
}
