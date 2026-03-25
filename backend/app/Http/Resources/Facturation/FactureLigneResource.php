<?php

declare(strict_types=1);

namespace App\Http\Resources\Facturation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FactureLigneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'facture_id' => $this->facture_id,
            'prestation_id' => $this->prestation_id,
            'designation' => $this->designation,
            'quantite' => $this->quantite,
            'prix_unitaire_ht' => $this->prix_unitaire_ht,
            'total_ht' => $this->total_ht,
            'ordre' => $this->ordre,
        ];
    }
}
