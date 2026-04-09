<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\StoreTacheRequest;
use App\Http\Requests\Planning\UpdateTacheRequest;
use App\Http\Resources\Planning\TacheResource;
use App\Models\Mission;
use App\Models\Tache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TacheController extends Controller
{
    public function index(Mission $mission): AnonymousResourceCollection
    {
        $this->authorize('view', $mission);

        return TacheResource::collection(
            $mission->taches()->with('assignee')->latest()->get()
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
        $this->authorize('view', $mission);

        return new TacheResource($tache->load('assignee'));
    }

    public function update(UpdateTacheRequest $request, Mission $mission, Tache $tache): TacheResource
    {
        $this->authorize('update', $tache);

        $data = $request->user()->hasAnyRole(['admin', 'secretaire'])
            ? $request->validated()
            : $request->only('statut');

        $tache->update($data);

        return new TacheResource($tache->load('assignee'));
    }

    public function destroy(Mission $mission, Tache $tache): JsonResponse
    {
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
