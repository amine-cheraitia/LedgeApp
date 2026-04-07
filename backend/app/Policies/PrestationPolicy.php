<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Prestation;
use App\Models\User;

class PrestationPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Prestation $prestation): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Prestation $prestation): bool
    {
        return $user->hasRole('admin');
    }
}
