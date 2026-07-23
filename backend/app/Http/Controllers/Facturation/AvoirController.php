<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturation\StoreAvoirRequest;
use App\Http\Resources\Facturation\AvoirResource;
use App\Models\Avoir;
use App\Models\Facture;
use App\Services\FacturationService;
use App\Services\PdfService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

class AvoirController extends Controller
{
    public function __construct(
        private readonly FacturationService $facturationService,
        private readonly PdfService $pdfService,
    ) {}

    public function indexAll(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Avoir::class);

        return AvoirResource::collection(
            $this->facturationService->listerAvoirs($request->only(['exercice_id', 'search', 'per_page']))
        );
    }

    public function index(Facture $facture): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Avoir::class);

        $avoirs = $facture->avoirs()->latest()->get();

        return AvoirResource::collection($avoirs);
    }

    public function destroy(Avoir $avoir): HttpResponse
    {
        $this->authorize('delete', $avoir);

        $this->facturationService->supprimerAvoir($avoir);

        return response()->noContent();
    }

    public function store(StoreAvoirRequest $request, Facture $facture): JsonResponse
    {
        $this->authorize('create', Avoir::class);

        try {
            $avoir = $this->facturationService->creerAvoir(
                $facture,
                $request->validated(),
                $request->user()->id,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new AvoirResource($avoir))
            ->response()
            ->setStatusCode(201);
    }

    public function pdf(Facture $facture, Avoir $avoir): HttpResponse
    {
        abort_if($avoir->facture_origine_id !== $facture->id, 404);

        return $this->pdfService->genererAvoir($avoir)
            ->download('avoir-'.$avoir->numero.'.pdf');
    }
}
