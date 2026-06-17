<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portail;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\UserResource;
use Illuminate\Http\Request;

class PortailController extends Controller
{
    /**
     * Retourne le profil du client connecte avec son entreprise.
     */
    public function me(Request $request): UserResource
    {
        $user = $request->user()->load('entreprise', 'roles');

        return new UserResource($user);
    }
}
