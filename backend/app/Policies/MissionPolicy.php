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

        return $mission->collaborateurs()->where('user_id', $user->id)->exists();
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
