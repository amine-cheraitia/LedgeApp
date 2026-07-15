<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\StatistiqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatistiqueController extends Controller
{
    public function __construct(private readonly StatistiqueService $statistiqueService) {}

    public function cabinet(Request $request): JsonResponse
    {
        // Defense en profondeur : la route est deja restreinte par le middleware role:admin.
        abort_unless($request->user()?->hasRole('admin'), 403);

        $request->validate([
            'exercice_id' => ['nullable', 'integer', 'exists:exercices,id'],
        ]);

        $exerciceId = $request->integer('exercice_id') ?: null;

        return response()->json([
            'data' => $this->statistiqueService->getCabinetStats($exerciceId),
        ]);
    }
}
