<?php

declare(strict_types=1);

namespace App\Http\Controllers\Referentiel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referentiel\StoreTvaTauxRequest;
use App\Http\Requests\Referentiel\UpdateTvaTauxRequest;
use App\Http\Resources\Referentiel\TvaTauxResource;
use App\Models\TvaTaux;
use App\Services\TvaTauxService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReferentielTvaController extends Controller
{
    public function __construct(private readonly TvaTauxService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return TvaTauxResource::collection(
            TvaTaux::orderBy('type')->orderByDesc('date_debut')->get()
        );
    }

    public function store(StoreTvaTauxRequest $request): JsonResponse
    {
        $this->authorize('create', TvaTaux::class);

        try {
            $taux = $this->service->creer($request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new TvaTauxResource($taux))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTvaTauxRequest $request, TvaTaux $tvaTaux): JsonResponse
    {
        $this->authorize('update', $tvaTaux);

        try {
            $taux = $this->service->mettreAJour($tvaTaux, $request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new TvaTauxResource($taux))->response();
    }

    public function destroy(TvaTaux $tvaTaux): JsonResponse
    {
        $this->authorize('delete', $tvaTaux);

        try {
            $this->service->supprimer($tvaTaux);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['message' => 'Taux de TVA supprime.']);
    }
}
