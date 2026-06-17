<?php

declare(strict_types=1);

namespace App\Http\Resources\Entreprises;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entreprise_id' => $this->entreprise_id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'poste' => $this->poste,
            'est_principal' => $this->est_principal,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
