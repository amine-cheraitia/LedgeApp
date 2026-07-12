<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Mission;
use App\Models\Tache;
use App\Models\User;
use DomainException;
use Illuminate\Support\Arr;

class TacheService
{
    public function creer(Mission $mission, array $data): Tache
    {
        return $mission->taches()->create($data)->load('assignee');
    }

    /**
     * L'admin edite tous les champs ; un collaborateur assigne ne peut changer que le statut.
     *
     * @param  array<string, mixed>  $data
     */
    public function mettreAJour(Tache $tache, array $data, User $user): Tache
    {
        $champs = $user->hasRole('admin') ? $data : Arr::only($data, ['statut']);

        $tache->update($champs);

        return $tache->load('assignee');
    }

    public function supprimer(Tache $tache): void
    {
        if ($tache->commentaires()->exists()) {
            throw new DomainException('Impossible de supprimer une tache avec des commentaires.');
        }

        $tache->delete();
    }
}
