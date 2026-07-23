<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Mission;
use App\Models\Tache;
use App\Models\TacheCommentaire;
use App\Models\User;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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

    /**
     * Ajoute un commentaire a une tache. Regle metier : le premier commentaire
     * ENGAGE la tache (a_faire -> en_cours) — une tache terminee/annulee n'est
     * pas reactivee. Transaction : le commentaire et le changement de statut
     * sont indissociables.
     */
    public function commenter(Tache $tache, User $auteur, string $contenu): TacheCommentaire
    {
        return DB::transaction(function () use ($tache, $auteur, $contenu) {
            $commentaire = $tache->commentaires()->create([
                'user_id' => $auteur->id,
                'contenu' => $contenu,
            ]);

            if ($tache->statut === 'a_faire') {
                $tache->update(['statut' => 'en_cours']);
            }

            return $commentaire;
        });
    }
}
