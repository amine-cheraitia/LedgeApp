<?php

declare(strict_types=1);

namespace App\Http\Resources\Facturation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvoirResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'facture_origine_id' => $this->facture_origine_id,
            'exercice_id' => $this->exercice_id,
            'created_by' => $this->created_by,
            'numero' => $this->numero,
            'date_avoir' => $this->date_avoir?->toDateString(),
            'montant_ht' => $this->montant_ht,
            'taux_tva_snapshot' => $this->taux_tva_snapshot,
            'montant_tva' => $this->montant_tva,
            'montant_ttc' => $this->montant_ttc,
            'motif' => $this->motif,
            'facture_origine' => $this->whenLoaded('factureOrigine', fn () => [
                'id' => $this->factureOrigine->id,
                'numero' => $this->factureOrigine->numero,
                'entreprise' => $this->factureOrigine->relationLoaded('entreprise')
                    ? ['raison_sociale' => $this->factureOrigine->entreprise?->raison_sociale]
                    : null,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
