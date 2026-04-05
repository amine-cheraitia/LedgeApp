<?php

declare(strict_types=1);

namespace App\Http\Resources\Planning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MissionResource extends JsonResource
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
            'prestation_id' => $this->prestation_id,
            'devis_id' => $this->devis_id,
            'reference' => $this->reference,
            'convention_numero' => $this->convention_numero,
            'mandat_numero' => $this->mandat_numero,
            'prix_ht' => (float) $this->prix_ht,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'statut' => $this->statut,
            'notes' => $this->notes,
            'entreprise' => $this->whenLoaded('entreprise'),
            'prestation' => $this->whenLoaded('prestation'),
            'exercice' => $this->whenLoaded('exercice'),
            'collaborateurs' => $this->whenLoaded('collaborateurs'),
            'taches' => TacheResource::collection($this->whenLoaded('taches')),
            'factures' => $this->whenLoaded('factures'),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
