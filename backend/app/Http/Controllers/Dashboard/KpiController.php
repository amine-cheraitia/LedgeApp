<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\KpiObjectif;
use App\Services\KpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KpiController extends Controller
{
    public function __construct(private readonly KpiService $kpiService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'exercice_id' => ['nullable', 'integer', 'exists:exercices,id'],
        ]);

        $data = $this->kpiService->getCollaborateurs($request->integer('exercice_id') ?: null);

        return response()->json(['data' => $data]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'exercice_id' => ['required', 'integer', 'exists:exercices,id'],
            'type' => ['required', 'in:ca_ht,missions_cloturees,delai_moyen_facturation'],
            'valeur' => ['required', 'numeric', 'min:0'],
        ]);

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
        $this->kpiService->supprimerObjectif($objectif);

        return response()->json(null, 204);
    }
}
