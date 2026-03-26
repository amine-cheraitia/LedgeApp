<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TacheCommentaire;
use App\Models\User;

class TacheCommentairePolicy
{
    public function update(User $user, TacheCommentaire $commentaire): bool
    {
        return $user->id === $commentaire->user_id || $user->hasRole('admin');
    }

    public function delete(User $user, TacheCommentaire $commentaire): bool
    {
        return $user->id === $commentaire->user_id || $user->hasRole('admin');
    }
}
