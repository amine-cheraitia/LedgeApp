<?php

declare(strict_types=1);

namespace App\Http\Controllers\Prestations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prestations\CalculerPrixRequest;
use App\Http\Requests\Prestations\StorePrestationRequest;
use App\Http\Requests\Prestations\UpdatePrestationRequest;
use App\Http\Resources\Prestations\PrestationResource;
use App\Models\Prestation;
use App\Services\PrestationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PrestationController extends Controller
{
    public function __construct(private readonly PrestationService $prestationService) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Prestation::class);

        return PrestationResource::collection(Prestation::all());
    }

    public function show(Prestation $prestation): PrestationResource
    {
        $this->authorize('view', $prestation);

        return new PrestationResource($prestation);
    }

    public function store(StorePrestationRequest $request): JsonResponse
    {
        $this->authorize('create', Prestation::class);

        $prestation = Prestation::create($request->validated() + ['actif' => $request->boolean('actif', true)]);

        return (new PrestationResource($prestation))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePrestationRequest $request, Prestation $prestation): PrestationResource
    {
        $this->authorize('update', $prestation);

        $prestation->update($request->validated());

        return new PrestationResource($prestation);
    }

    public function destroy(Prestation $prestation): JsonResponse
    {
        $this->authorize('delete', $prestation);

        try {
            $this->prestationService->supprimer($prestation);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['message' => 'Prestation supprimee.']);
    }

    public function calculerPrix(CalculerPrixRequest $request, Prestation $prestation): JsonResponse
    {
        $this->authorize('viewAny', Prestation::class);

        $prixHt = $prestation->calculerPrixHt(
            $request->regime_fiscal,
            $request->categorie
        );

        return response()->json([
            'prestation' => $prestation->code,
            'tarif_initial' => $prestation->tarif_initial,
            'regime_fiscal' => $request->regime_fiscal,
            'categorie' => $request->categorie,
            'prix_ht' => $prixHt,
        ]);
    }
}
