<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Prestation;
use DomainException;

class PrestationService
{
    public function supprimer(Prestation $prestation): void
    {
        if ($prestation->missions()->exists()) {
            throw new DomainException('Impossible de supprimer une prestation liee a des missions.');
        }

        $prestation->delete();
    }
}
