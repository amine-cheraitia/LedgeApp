<?php

declare(strict_types=1);

namespace App\Http\Resources\Facturation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FactureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entreprise_id' => $this->entreprise_id,
            'exercice_id' => $this->exercice_id,
            'mission_id' => $this->mission_id,
            'devis_id' => $this->devis_id,
            'created_by' => $this->created_by,
            'numero' => $this->numero,
            'type' => $this->type,
            'date_facture' => $this->date_facture?->toDateString(),
            'date_echeance' => $this->date_echeance?->toDateString(),
            'montant_ht' => $this->montant_ht,
            'taux_tva' => $this->taux_tva,
            'montant_tva' => $this->montant_tva,
            'montant_ttc' => $this->montant_ttc,
            'montant_paye' => $this->montant_paye,
            'statut_paiement' => $this->statut_paiement,
            'mode_paiement' => $this->mode_paiement,
            'montant_restant' => $this->montantRestant(),
            'entreprise' => $this->whenLoaded('entreprise'),
            'mission' => $this->whenLoaded('mission'),
            'lignes' => FactureLigneResource::collection($this->whenLoaded('lignes')),
            'paiements' => PaiementResource::collection($this->whenLoaded('paiements')),
            'relances' => RelanceResource::collection($this->whenLoaded('relances')),
            'avoirs' => AvoirResource::collection($this->whenLoaded('avoirs')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
