<?php

namespace App\Http\Resources\Entreprises;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntrepriseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'raison_sociale' => $this->raison_sociale,
            'nif' => $this->nif,
            'nis' => $this->nis,
            'num_rc' => $this->num_rc,
            'article_imposition' => $this->article_imposition,
            'regime_fiscal' => $this->regime_fiscal,
            'categorie' => $this->categorie,
            'secteur_activite' => $this->secteur_activite,
            'adresse' => $this->adresse,
            'ville' => $this->ville,
            'wilaya' => $this->wilaya,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'contact_principal' => $this->contact_principal,
            'statut' => $this->statut,
            'notes' => $this->notes,
            'missions_count' => $this->whenCounted('missions'),
            'factures_count' => $this->whenCounted('factures'),
            'portail_user' => $this->whenLoaded('users', function () {
                $clientUser = $this->users->first(fn ($u) => $u->hasRole('client'));

                return $clientUser ? [
                    'id' => $clientUser->id,
                    'name' => $clientUser->name,
                    'email' => $clientUser->email,
                    'portail_actif' => $clientUser->portail_actif,
                ] : null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
