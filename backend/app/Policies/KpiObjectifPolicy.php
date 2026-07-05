<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KpiObjectif;
use App\Models\User;

class KpiObjectifPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, KpiObjectif $objectif): bool
    {
        return $user->hasRole('admin');
    }
}
