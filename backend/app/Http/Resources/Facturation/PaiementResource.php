<?php

declare(strict_types=1);

namespace App\Http\Resources\Facturation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaiementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'facture_id' => $this->facture_id,
            'recorded_by' => $this->recorded_by,
            'montant' => $this->montant,
            'date_paiement' => $this->date_paiement?->toDateString(),
            'mode_paiement' => $this->mode_paiement,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
