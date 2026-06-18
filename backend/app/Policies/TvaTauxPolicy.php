<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TvaTaux;
use App\Models\User;

class TvaTauxPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, TvaTaux $tvaTaux): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, TvaTaux $tvaTaux): bool
    {
        return $user->hasRole('admin');
    }
}
