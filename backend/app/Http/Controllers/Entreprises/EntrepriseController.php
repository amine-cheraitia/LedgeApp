<?php

namespace App\Http\Controllers\Entreprises;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entreprises\StoreEntrepriseRequest;
use App\Http\Requests\Entreprises\UpdateEntrepriseRequest;
use App\Http\Resources\Entreprises\EntrepriseResource;
use App\Models\Entreprise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EntrepriseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $entreprises = Entreprise::query()
            ->when($request->search, fn ($q, $s) => $q->where('raison_sociale', 'like', "%{$s}%")->orWhere('nif', 'like', "%{$s}%"))
            ->when($request->statut, fn ($q, $s) => $q->where('statut', $s))
            ->withCount('missions', 'factures')
            ->latest()
            ->paginate($request->per_page ?? 15);

        return EntrepriseResource::collection($entreprises);
    }

    public function store(StoreEntrepriseRequest $request): JsonResponse
    {
        $entreprise = Entreprise::create($request->validated());

        return (new EntrepriseResource($entreprise))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Entreprise $entreprise): EntrepriseResource
    {
        $entreprise->loadCount('missions', 'factures');

        return new EntrepriseResource($entreprise);
    }

    public function update(UpdateEntrepriseRequest $request, Entreprise $entreprise): EntrepriseResource
    {
        $entreprise->update($request->validated());

        return new EntrepriseResource($entreprise);
    }

    public function destroy(Entreprise $entreprise): JsonResponse
    {
        if ($entreprise->missions()->exists() || $entreprise->devis()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer cette entreprise : des missions ou devis y sont associes.',
            ], 409);
        }

        $entreprise->delete();

        return response()->json(['message' => 'Entreprise supprimee.']);
    }
}
