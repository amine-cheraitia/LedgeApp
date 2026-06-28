<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\StoreTacheCommentaireRequest;
use App\Http\Requests\Planning\UpdateTacheCommentaireRequest;
use App\Http\Resources\Planning\TacheCommentaireResource;
use App\Models\Tache;
use App\Models\TacheCommentaire;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class TacheCommentaireController extends Controller
{
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

        $commentaire = DB::transaction(function () use ($request, $tache) {
            $commentaire = $tache->commentaires()->create([
                'user_id' => $request->user()->id,
                'contenu' => $request->validated('contenu'),
            ]);

            // Le 1er commentaire engage la tache : a_faire -> en_cours
            if ($tache->statut === 'a_faire') {
                $tache->update(['statut' => 'en_cours']);
            }

            return $commentaire;
        });

        return (new TacheCommentaireResource($commentaire->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTacheCommentaireRequest $request, Tache $tache, TacheCommentaire $commentaire): TacheCommentaireResource
    {
        $this->authorize('update', $commentaire);

        $commentaire->update($request->validated());

        return new TacheCommentaireResource($commentaire->load('user'));
    }

    public function destroy(Tache $tache, TacheCommentaire $commentaire): JsonResponse
    {
        $this->authorize('delete', $commentaire);

        $commentaire->delete();

        return response()->json(null, 204);
    }
}
