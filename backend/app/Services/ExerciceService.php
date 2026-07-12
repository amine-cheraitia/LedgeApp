<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Exercice;
use DomainException;

class ExerciceService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function mettreAJour(Exercice $exercice, array $data): Exercice
    {
        // On ne rouvre pas un exercice cloture qui porte deja des documents : cela
        // contournerait la separation stricte par annee.
        $reouverture = ($data['statut'] ?? null) === 'ouvert' && $exercice->statut === 'cloture';
        if ($reouverture && $this->porteDesDocuments($exercice)) {
            throw new DomainException('Impossible de rouvrir un exercice clôturé qui porte des missions, devis ou factures.');
        }

        $exercice->update($data);

        return $exercice;
    }

    public function supprimer(Exercice $exercice): void
    {
        if ($this->porteDesDocuments($exercice)) {
            throw new DomainException('Impossible de supprimer un exercice ayant des missions, devis ou factures associes.');
        }

        $exercice->delete();
    }

    private function porteDesDocuments(Exercice $exercice): bool
    {
        return $exercice->missions()->exists()
            || $exercice->devis()->exists()
            || $exercice->factures()->exists();
    }
}
