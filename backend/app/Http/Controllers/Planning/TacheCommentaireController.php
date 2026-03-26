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

class TacheCommentaireController extends Controller
{
    public function index(Tache $tache): AnonymousResourceCollection
    {
        return TacheCommentaireResource::collection(
            $tache->commentaires()->with('user')->latest()->get()
        );
    }

    public function store(StoreTacheCommentaireRequest $request, Tache $tache): JsonResponse
    {
        $commentaire = $tache->commentaires()->create([
            'user_id' => $request->user()->id,
            'contenu' => $request->validated('contenu'),
        ]);

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
