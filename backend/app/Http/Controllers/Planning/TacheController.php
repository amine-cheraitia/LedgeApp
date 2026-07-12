<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\CheckTacheConflitsRequest;
use App\Http\Requests\Planning\StoreTacheRequest;
use App\Http\Requests\Planning\UpdateTacheRequest;
use App\Http\Resources\Planning\ConflitTacheResource;
use App\Http\Resources\Planning\TacheResource;
use App\Models\Mission;
use App\Models\Tache;
use App\Services\MissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TacheController extends Controller
{
    public function __construct(private readonly MissionService $missionService) {}

    /**
     * Liste les tâches du collaborateur qui chevauchent la période visée
     * (toutes missions confondues), pour avertir l'admin d'un conflit d'affectation.
     */
    public function conflits(CheckTacheConflitsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('create', Mission::class);

        $data = $request->validated();

        $conflits = $this->missionService->detecterConflitsTache(
            (int) $data['collaborateur_id'],
            $data['date_debut'] ?? null,
            $data['date_echeance'] ?? null,
            isset($data['exclude_tache_id']) ? (int) $data['exclude_tache_id'] : null,
        );

        return ConflitTacheResource::collection($conflits);
    }

    public function index(Request $request, Mission $mission): AnonymousResourceCollection
    {
        $this->authorize('view', $mission);

        // Le collaborateur ne voit que ses propres tâches au sein de la mission.
        return TacheResource::collection(
            $mission->taches()->visiblePour($request->user())->with('assignee')->latest()->get()
        );
    }

    public function store(StoreTacheRequest $request, Mission $mission): JsonResponse
    {
        $this->authorize('create', Mission::class);

        $tache = $mission->taches()->create($request->validated());

        return (new TacheResource($tache->load('assignee')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Mission $mission, Tache $tache): TacheResource
    {
        abort_if($tache->mission_id !== $mission->id, 404, 'Tache introuvable pour cette mission.');

        $this->authorize('view', $tache);

        return new TacheResource($tache->load('assignee'));
    }

    public function update(UpdateTacheRequest $request, Mission $mission, Tache $tache): TacheResource
    {
        abort_if($tache->mission_id !== $mission->id, 404, 'Tache introuvable pour cette mission.');

        $this->authorize('update', $tache);

        // Le Planning est reserve admin + collaborateur (la secretaire n'atteint jamais cette route) :
        // l'admin edite tous les champs, le collaborateur assigne ne change que le statut.
        $data = $request->user()->hasRole('admin')
            ? $request->validated()
            : $request->only('statut');

        $tache->update($data);

        return new TacheResource($tache->load('assignee'));
    }

    public function destroy(Mission $mission, Tache $tache): JsonResponse
    {
        abort_if($tache->mission_id !== $mission->id, 404, 'Tache introuvable pour cette mission.');

        $this->authorize('delete', $tache);

        if ($tache->commentaires()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une tache avec des commentaires.',
            ], 409);
        }

        $tache->delete();

        return response()->json(null, 204);
    }
}
