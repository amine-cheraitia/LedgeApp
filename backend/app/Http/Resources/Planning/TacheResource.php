<?php

declare(strict_types=1);

namespace App\Http\Resources\Planning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TacheResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mission_id' => $this->mission_id,
            'assigned_to' => $this->assigned_to,
            'titre' => $this->titre,
            'description' => $this->description,
            'statut' => $this->statut,
            'date_echeance' => $this->date_echeance?->toDateString(),
            'priorite' => $this->priorite,
            'assignee' => $this->whenLoaded('assignee'),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
