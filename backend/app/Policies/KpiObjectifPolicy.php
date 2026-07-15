<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KpiObjectif;
use App\Models\User;

class KpiObjectifPolicy
{
    public function viewAny(User $user): bool
    {
        // Les KPI de production par collaborateur (CA HT, missions cloturees) sont
        // des donnees internes reservees a l'admin : hors perimetre de la secretaire.
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, KpiObjectif $objectif): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Autorise la vue des stats detaillees d'un collaborateur (page /statistiques) :
     * l'admin voit tout le monde ; le collaborateur ne voit que lui-meme.
     * Le self-view prepare la phase 2 (Mes objectifs sur le dashboard collaborateur).
     */
    public function viewStats(User $auth, User $collaborateur): bool
    {
        return $auth->hasRole('admin')
            || ($auth->id === $collaborateur->id && $auth->hasAnyRole(['collaborateur', 'admin']));
    }
}
