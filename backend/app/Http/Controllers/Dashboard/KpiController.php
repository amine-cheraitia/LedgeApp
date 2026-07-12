<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreKpiObjectifRequest;
use App\Models\KpiObjectif;
use App\Services\KpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KpiController extends Controller
{
    public function __construct(private readonly KpiService $kpiService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', KpiObjectif::class);

        $request->validate([
            'exercice_id' => ['nullable', 'integer', 'exists:exercices,id'],
        ]);

        $data = $this->kpiService->getCollaborateurs($request->integer('exercice_id') ?: null);

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
}
