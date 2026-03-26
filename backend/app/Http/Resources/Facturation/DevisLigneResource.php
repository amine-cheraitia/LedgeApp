<?php

declare(strict_types=1);

namespace App\Http\Resources\Facturation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevisLigneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'devis_id' => $this->devis_id,
            'prestation_id' => $this->prestation_id,
            'designation' => $this->designation,
            'quantite' => $this->quantite,
            'prix_unitaire_ht' => $this->prix_unitaire_ht,
            'total_ht' => $this->total_ht,
            'ordre' => $this->ordre,
        ];
    }
}
