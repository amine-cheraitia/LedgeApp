<?php

declare(strict_types=1);

namespace App\Http\Resources\Facturation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RelanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'facture_id' => $this->facture_id,
            'sent_by' => $this->sent_by,
            'niveau' => $this->niveau,
            'type' => $this->type,
            'email_destinataire' => $this->email_destinataire,
            'envoyee_le' => $this->envoyee_le?->toIso8601String(),
            'statut' => $this->statut,
            'message' => $this->message,
            'sentBy' => $this->whenLoaded('sentBy'),
            'created_at' => $this->created_at,
        ];
    }
}
