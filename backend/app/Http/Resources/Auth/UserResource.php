<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'entreprise_id' => $this->entreprise_id,
            'portail_actif' => $this->portail_actif,
            'email_verified_at' => $this->email_verified_at,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'entreprise' => $this->whenLoaded('entreprise'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
