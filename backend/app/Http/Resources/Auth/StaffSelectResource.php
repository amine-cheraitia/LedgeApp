<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue minimale d'un membre du personnel pour les selects d'assignation
 * (missions, taches, devis). N'expose QUE id / name / roles : aucune donnee
 * sensible (email, entreprise_id, portail_actif) n'est divulguee aux roles
 * non-admin, et les clients ne figurent jamais dans ces listes.
 */
class StaffSelectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
        ];
    }
}
