<?php

declare(strict_types=1);

namespace App\Http\Controllers\Referentiel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referentiel\StoreTvaTauxRequest;
use App\Http\Requests\Referentiel\UpdateTvaTauxRequest;
use App\Http\Resources\Referentiel\TvaTauxResource;
use App\Models\Facture;
use App\Models\TvaTaux;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReferentielTvaController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return TvaTauxResource::collection(
            TvaTaux::orderBy('type')->orderByDesc('date_debut')->get()
        );
    }

    public function store(StoreTvaTauxRequest $request): JsonResponse
    {
        $this->authorize('create', TvaTaux::class);

        $taux = TvaTaux::create($request->validated() + ['actif' => $request->boolean('actif', true)]);

        return (new TvaTauxResource($taux))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTvaTauxRequest $request, TvaTaux $tvaTaux): TvaTauxResource
    {
        $this->authorize('update', $tvaTaux);

        $tvaTaux->update($request->validated());

        return new TvaTauxResource($tvaTaux);
    }

    public function destroy(TvaTaux $tvaTaux): JsonResponse
    {
        $this->authorize('delete', $tvaTaux);

        if (Facture::where('tva_taux_id', $tvaTaux->id)->exists()) {
            return response()->json(['message' => 'Impossible de supprimer un taux de TVA utilise par des factures.'], 409);
        }

        $tvaTaux->delete();

        return response()->json(['message' => 'Taux de TVA supprime.']);
    }
}
