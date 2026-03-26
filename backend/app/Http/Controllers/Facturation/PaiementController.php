<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturation;

use App\Events\InvoicePaid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Facturation\StorePaiementRequest;
use App\Http\Resources\Facturation\PaiementResource;
use App\Models\Facture;
use App\Models\Paiement;
use App\Services\FacturationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaiementController extends Controller
{
    public function __construct(private FacturationService $facturationService) {}

    public function index(Facture $facture): AnonymousResourceCollection
    {
        return PaiementResource::collection(
            $facture->paiements()->latest('date_paiement')->get()
        );
    }

    public function store(StorePaiementRequest $request, Facture $facture): JsonResponse
    {
        if ($facture->estSolde()) {
            return response()->json([
                'message' => 'Cette facture est deja soldee.',
            ], 409);
        }

        $paiement = Paiement::create([
            'facture_id' => $facture->id,
            'recorded_by' => $request->user()->id,
            'montant' => $request->validated('montant'),
            'date_paiement' => $request->validated('date_paiement'),
            'mode_paiement' => $request->validated('mode_paiement'),
            'reference' => $request->validated('reference'),
            'notes' => $request->validated('notes'),
        ]);

        $this->facturationService->recalculerStatutPaiement($facture);

        if ($facture->estSolde()) {
            InvoicePaid::dispatch($facture);
        }

        return (new PaiementResource($paiement))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Facture $facture, Paiement $paiement): JsonResponse
    {
        $paiement->delete();
        $this->facturationService->recalculerStatutPaiement($facture);

        return response()->json(['message' => 'Paiement supprime.']);
    }
}
