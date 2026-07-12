<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Prestation;
use App\Models\User;

class PrestationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function view(User $user, Prestation $prestation): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

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
