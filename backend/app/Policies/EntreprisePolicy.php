<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Entreprise;
use App\Models\User;

class EntreprisePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function view(User $user, Entreprise $entreprise): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Entreprise $entreprise): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Entreprise $entreprise): bool
    {
        return $user->hasRole('admin');
    }
}
