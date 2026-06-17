<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturation\StoreFactureRequest;
use App\Http\Resources\Facturation\FactureResource;
use App\Models\Facture;
use App\Services\FacturationService;
use App\Services\PdfService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

class FactureController extends Controller
{
    public function __construct(
        private readonly FacturationService $facturationService,
        private readonly PdfService $pdfService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Facture::class);

        $factures = $this->facturationService->listerFactures($request->only([
            'entreprise_id', 'exercice_id', 'statut_paiement', 'type', 'search', 'sort_field', 'sort_direction', 'per_page',
        ]));

        return FactureResource::collection($factures);
    }

    public function store(StoreFactureRequest $request): JsonResponse
    {
        $this->authorize('create', Facture::class);

        try {
            $facture = $this->facturationService->creerFacture(
                $request->validated(),
                $request->user()->id,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new FactureResource($facture->load('lignes', 'entreprise', 'mission')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Facture $facture): FactureResource
    {
        $this->authorize('view', $facture);

        return new FactureResource(
            $facture->load('lignes', 'entreprise', 'mission', 'paiements')
        );
    }

    public function pdf(Facture $facture): HttpResponse
    {
        $this->authorize('view', $facture);

        return $this->pdfService->genererFacture($facture)
            ->download('facture-'.$facture->numero.'.pdf');
    }

    public function destroy(Facture $facture): JsonResponse
    {
        $this->authorize('delete', $facture);

        try {
            $this->facturationService->supprimerFacture($facture);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(null, 204);
    }
}
