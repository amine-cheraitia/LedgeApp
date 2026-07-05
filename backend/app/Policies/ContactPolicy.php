<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->hasAnyRole(['admin', 'secretaire']);
    }
}
