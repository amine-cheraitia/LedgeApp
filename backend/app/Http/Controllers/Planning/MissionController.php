<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\StoreMissionRequest;
use App\Http\Requests\Planning\UpdateMissionRequest;
use App\Http\Resources\Planning\MissionResource;
use App\Models\Mission;
use App\Services\MissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MissionController extends Controller
{
    public function __construct(
        private MissionService $missionService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $missions = Mission::with('entreprise', 'prestation', 'exercice')
            ->when(request('entreprise_id'), fn ($q, $v) => $q->where('entreprise_id', $v))
            ->when(request('statut'), fn ($q, $v) => $q->where('statut', $v))
            ->when(request('search'), fn ($q, $v) => $q->where('reference', 'like', "%{$v}%"))
            ->latest()
            ->paginate(request('per_page', 15));

        return MissionResource::collection($missions);
    }

    public function store(StoreMissionRequest $request): JsonResponse
    {
        $mission = $this->missionService->creerMission($request->validated());

        return (new MissionResource($mission))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Mission $mission): MissionResource
    {
        return new MissionResource(
            $mission->load('entreprise', 'prestation', 'exercice', 'collaborateurs', 'taches.assignee', 'factures')
        );
    }

    public function update(UpdateMissionRequest $request, Mission $mission): MissionResource
    {
        $mission->update($request->safe()->except(['collaborateur_ids']));

        if ($request->has('collaborateur_ids')) {
            $mission->collaborateurs()->sync($request->validated('collaborateur_ids') ?? []);
        }

        return new MissionResource($mission->load('entreprise', 'prestation', 'collaborateurs'));
    }

    public function destroy(Mission $mission): JsonResponse
    {
        if ($mission->factures()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une mission avec des factures associees.',
            ], 409);
        }

        $mission->taches()->delete();
        $mission->delete();

        return response()->json(null, 204);
    }
}
