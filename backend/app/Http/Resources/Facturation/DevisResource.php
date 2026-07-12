<?php

declare(strict_types=1);

namespace App\Http\Resources\Facturation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entreprise_id' => $this->entreprise_id,
            'prestation_id' => $this->prestation_id,
            'exercice_id' => $this->exercice_id,
            'created_by' => $this->created_by,
            'numero' => $this->numero,
            'date_devis' => $this->date_devis?->toDateString(),
            'date_validite' => $this->date_validite?->toDateString(),
            'prix_ht' => $this->prix_ht,
            'taux_tva' => $this->taux_tva,
            'montant_tva' => $this->montant_tva,
            'montant_ttc' => $this->montant_ttc,
            'statut' => $this->statut,
            'entreprise' => $this->whenLoaded('entreprise'),
            'prestation' => $this->whenLoaded('prestation'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
