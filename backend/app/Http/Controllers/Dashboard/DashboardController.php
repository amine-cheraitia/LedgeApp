<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function stats(Request $request): JsonResponse
    {
        $exerciceId = $request->integer('exercice_id') ?: null;

        return response()->json([
            'data' => $this->dashboardService->getStats($exerciceId),
        ]);
    }

    public function collaborateurStats(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboardService->getCollaborateurStats($request->user()),
        ]);
    }
}
