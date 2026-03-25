<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\StoreTacheRequest;
use App\Http\Resources\Planning\TacheResource;
use App\Models\Mission;
use App\Models\Tache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TacheController extends Controller
{
    public function index(Mission $mission): AnonymousResourceCollection
    {
        return TacheResource::collection(
            $mission->taches()->with('assignee')->latest()->get()
        );
    }

    public function store(StoreTacheRequest $request, Mission $mission): JsonResponse
    {
        $tache = $mission->taches()->create($request->validated());

        return (new TacheResource($tache->load('assignee')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(StoreTacheRequest $request, Mission $mission, Tache $tache): TacheResource
    {
        $tache->update($request->validated());

        return new TacheResource($tache->load('assignee'));
    }

    public function destroy(Mission $mission, Tache $tache): JsonResponse
    {
        if ($tache->commentaires()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une tache avec des commentaires.',
            ], 409);
        }

        $tache->delete();

        return response()->json(null, 204);
    }
}
