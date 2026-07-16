<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\FiltreExerciceRequest;
use App\Services\StatistiqueService;
use Illuminate\Http\JsonResponse;

class StatistiqueController extends Controller
{
    public function __construct(private readonly StatistiqueService $statistiqueService) {}

    public function cabinet(FiltreExerciceRequest $request): JsonResponse
    {
        // Defense en profondeur : la route est deja restreinte par le middleware role:admin.
        abort_unless($request->user()?->hasRole('admin'), 403);

        return response()->json([
            'data' => $this->statistiqueService->getCabinetStats($request->exerciceId()),
        ]);
    }
}
