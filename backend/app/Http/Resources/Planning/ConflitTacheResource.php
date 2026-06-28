<?php

declare(strict_types=1);

namespace App\Http\Resources\Planning;

use App\Models\Tache;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload léger d'une tâche en conflit, pour l'avertissement réactif côté admin.
 *
 * @mixin Tache
 */
class ConflitTacheResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_echeance' => $this->date_echeance?->toDateString(),
            'mission' => [
                'id' => $this->mission?->id,
                'reference' => $this->mission?->reference,
            ],
        ];
    }
}
