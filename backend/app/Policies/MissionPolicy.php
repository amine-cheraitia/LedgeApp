<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Mission;
use App\Models\User;

class MissionPolicy
{
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function update(User $user, Mission $mission): bool
    {
        return $user->hasRole('admin')
            || $mission->collaborateurs()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, Mission $mission): bool
    {
        return $user->hasRole('admin');
    }
}
