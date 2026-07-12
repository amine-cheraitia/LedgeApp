<?php

declare(strict_types=1);

namespace App\Http\Resources\Prestations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrestationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'designation' => $this->designation,
            'tarif_initial' => $this->tarif_initial,
            'duree_mois' => $this->duree_mois,
            'description' => $this->description,
            'actif' => $this->actif,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
