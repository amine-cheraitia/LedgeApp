<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\FiltreExerciceRequest;
use App\Http\Requests\Dashboard\StoreKpiObjectifRequest;
use App\Models\KpiObjectif;
use App\Models\User;
use App\Services\KpiService;
use Illuminate\Http\JsonResponse;

class KpiController extends Controller
{
    public function __construct(private readonly KpiService $kpiService) {}

    public function index(FiltreExerciceRequest $request): JsonResponse
    {
        $this->authorize('viewAny', KpiObjectif::class);

        $data = $this->kpiService->getCollaborateurs($request->exerciceId());

        return response()->json(['data' => $data]);
    }

    public function upsert(StoreKpiObjectifRequest $request): JsonResponse
    {
        $this->authorize('create', KpiObjectif::class);

        $validated = $request->validated();

        $objectif = $this->kpiService->upsertObjectif(
            $validated['user_id'],
            $validated['exercice_id'],
            $validated['type'],
            (float) $validated['valeur']
        );

        return response()->json(['data' => $objectif], 200);
    }

    public function destroy(KpiObjectif $objectif): JsonResponse
    {
        $this->authorize('delete', $objectif);

        $this->kpiService->supprimerObjectif($objectif);

        return response()->json(null, 204);
    }

    /**
     * Stats detaillees d'un collaborateur (page /statistiques, onglet KPI).
     * La secretaire n'est jamais une cible valide -> 404 (pas 403, la ressource
     * n'existe pas dans ce contexte pour eviter de reveler les roles existants).
     */
    public function collaborateurStats(FiltreExerciceRequest $request, User $user): JsonResponse
    {
        abort_if(! $user->hasAnyRole(['admin', 'collaborateur']), 404);

        $this->authorize('viewStats', [KpiObjectif::class, $user]);

        return response()->json([
            'data' => $this->kpiService->getCollaborateurStats($user, $request->exerciceId()),
        ]);
    }
}
