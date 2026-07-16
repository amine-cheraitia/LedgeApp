<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\StoreTacheCommentaireRequest;
use App\Http\Requests\Planning\UpdateTacheCommentaireRequest;
use App\Http\Resources\Planning\TacheCommentaireResource;
use App\Models\Tache;
use App\Models\TacheCommentaire;
use App\Services\TacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TacheCommentaireController extends Controller
{
    public function __construct(private readonly TacheService $tacheService) {}

    public function index(Tache $tache): AnonymousResourceCollection
    {
        $this->authorize('view', $tache);

        return TacheCommentaireResource::collection(
            $tache->commentaires()->with('user')->latest()->get()
        );
    }

    public function store(StoreTacheCommentaireRequest $request, Tache $tache): JsonResponse
    {
        // Seul un utilisateur pouvant voir la tâche (admin ou collaborateur affecté) peut commenter.
        $this->authorize('view', $tache);

        $commentaire = $this->tacheService->commenter(
            $tache,
            $request->user(),
            $request->validated('contenu'),
        );

        return (new TacheCommentaireResource($commentaire->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTacheCommentaireRequest $request, Tache $tache, TacheCommentaire $commentaire): TacheCommentaireResource
    {
        abort_if($commentaire->tache_id !== $tache->id, 404, 'Commentaire introuvable pour cette tache.');

        $this->authorize('update', $commentaire);

        $commentaire->update($request->validated());

        return new TacheCommentaireResource($commentaire->load('user'));
    }

    public function destroy(Tache $tache, TacheCommentaire $commentaire): JsonResponse
    {
        abort_if($commentaire->tache_id !== $tache->id, 404, 'Commentaire introuvable pour cette tache.');

        $this->authorize('delete', $commentaire);

        $commentaire->delete();

        return response()->json(null, 204);
    }
}
