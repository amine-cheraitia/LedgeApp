<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturation\StoreFactureRequest;
use App\Http\Resources\Facturation\FactureResource;
use App\Models\Facture;
use App\Services\FacturationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FactureController extends Controller
{
    public function __construct(private FacturationService $facturationService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $factures = Facture::with('entreprise', 'lignes')
            ->when($request->entreprise_id, fn ($q, $id) => $q->where('entreprise_id', $id))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->statut_paiement, fn ($q, $s) => $q->where('statut_paiement', $s))
            ->when($request->search, fn ($q, $s) => $q->where('numero', 'like', "%{$s}%"))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return FactureResource::collection($factures);
    }

    public function store(StoreFactureRequest $request): JsonResponse
    {
        $facture = $this->facturationService->creerFacture(
            $request->safe()->except('lignes'),
            $request->validated('lignes'),
            $request->user()->id,
        );

        return (new FactureResource($facture->load('lignes', 'entreprise')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Facture $facture): FactureResource
    {
        return new FactureResource(
            $facture->load('lignes', 'entreprise', 'mission', 'paiements')
        );
    }

    public function destroy(Facture $facture): JsonResponse
    {
        if ($facture->paiements()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une facture avec des paiements.',
            ], 409);
        }

        $facture->lignes()->delete();
        $facture->delete();

        return response()->json(['message' => 'Facture supprimee.']);
    }
}
