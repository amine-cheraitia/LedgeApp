<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Avoir;
use App\Models\User;

class AvoirPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Avoir $avoir): bool
    {
        return $user->hasRole('admin');
    }
}
