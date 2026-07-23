<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tache;
use App\Models\User;

class TachePolicy
{
    public function view(User $user, Tache $tache): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('collaborateur') && $tache->assigned_to === $user->id;
    }

    public function update(User $user, Tache $tache): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('collaborateur') && $tache->assigned_to === $user->id;
    }

    public function delete(User $user, Tache $tache): bool
    {
        return $user->hasRole('admin');
    }
}
