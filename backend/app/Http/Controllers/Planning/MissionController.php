<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\StoreMissionRequest;
use App\Http\Requests\Planning\UpdateMissionRequest;
use App\Http\Resources\Planning\MissionResource;
use App\Models\Mission;
use App\Services\MissionService;
use App\Services\PdfService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class MissionController extends Controller
{
    public function __construct(
        private readonly MissionService $missionService,
        private readonly PdfService $pdfService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Mission::class);

        $missions = $this->missionService->listerMissions(
            $request->only(['entreprise_id', 'exercice_id', 'statut', 'search', 'sort_field', 'sort_direction', 'per_page']),
            $request->user(),
        );

        return MissionResource::collection($missions);
    }

    public function store(StoreMissionRequest $request): JsonResponse
    {
        $this->authorize('create', Mission::class);

        $mission = $this->missionService->creerMission($request->validated());

        return (new MissionResource($mission))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Mission $mission): MissionResource
    {
        $this->authorize('view', $mission);

        return new MissionResource(
            $mission->load('entreprise', 'prestation', 'exercice', 'collaborateurs', 'taches.assignee', 'factures')
        );
    }

    public function update(UpdateMissionRequest $request, Mission $mission): MissionResource
    {
        $this->authorize('update', $mission);

        $mission = $this->missionService->mettreAJourMission($mission, $request->validated());

        return new MissionResource($mission);
    }

    public function rapportPdf(Mission $mission): Response
    {
        $this->authorize('view', $mission);

        return $this->pdfService
            ->genererRapportMission($mission, false)
            ->stream('rapport-mission-'.$mission->reference.'.pdf');
    }

    public function conventionPdf(Mission $mission): Response
    {
        $this->authorize('view', $mission);

        $this->missionService->obtenirNumeroConvention($mission);
        $mission->refresh();

        return $this->pdfService->genererConvention($mission)
            ->stream('convention-'.$mission->convention_numero.'.pdf');
    }

    public function mandatPdf(Mission $mission): Response
    {
        $this->authorize('view', $mission);

        $this->missionService->obtenirNumeroMandat($mission);
        $mission->refresh();

        return $this->pdfService->genererMandat($mission)
            ->stream('mandat-'.$mission->mandat_numero.'.pdf');
    }

    public function destroy(Mission $mission): JsonResponse
    {
        $this->authorize('delete', $mission);

        try {
            $this->missionService->supprimerMission($mission);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(null, 204);
    }
}
