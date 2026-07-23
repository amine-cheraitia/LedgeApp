<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Exercice;
use DomainException;

class ExerciceService
{
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
