<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portail;

use App\Http\Controllers\Controller;
use App\Http\Resources\Planning\MissionResource;
use App\Models\Mission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PortailMissionController extends Controller
{
    /**
     * Liste les missions de l'entreprise du client connecte.
     * Scope isolation : entreprise_id du user connecte uniquement.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $entrepriseId = $request->user()->entreprise_id;

        $missions = Mission::with('prestation', 'exercice')
            ->where('entreprise_id', $entrepriseId)
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return MissionResource::collection($missions);
    }

    /**
     * Detail d'une mission avec ses taches (sans commentaires internes).
     */
    public function show(Request $request, Mission $mission): MissionResource
    {
        abort_if(
            $mission->entreprise_id !== $request->user()->entreprise_id,
            403,
            'Acces non autorise.'
        );

        // Taches chargees sans commentaires internes — lecture seule stricte
        return new MissionResource(
            $mission->load('prestation', 'exercice', 'taches.assignee')
        );
    }
}
