<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Exercice;
use App\Models\User;

class ExercicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function view(User $user, Exercice $exercice): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Exercice $exercice): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Exercice $exercice): bool
    {
        return $user->hasRole('admin');
    }
}
