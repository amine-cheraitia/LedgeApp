<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturation\StorePaiementRequest;
use App\Http\Resources\Facturation\PaiementResource;
use App\Models\Facture;
use App\Models\Paiement;
use App\Services\FacturationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaiementController extends Controller
{
    public function __construct(private readonly FacturationService $facturationService) {}

    public function index(Facture $facture): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Paiement::class);

        return PaiementResource::collection(
            $facture->paiements()->with('recordedBy')->latest('date_paiement')->get()
        );
    }

    public function store(StorePaiementRequest $request, Facture $facture): JsonResponse
    {
        $this->authorize('create', Paiement::class);

        try {
            $paiement = $this->facturationService->enregistrerPaiement(
                $facture,
                $request->validated(),
                $request->user()->id,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new PaiementResource($paiement))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Facture $facture, Paiement $paiement): JsonResponse
    {
        // Le paiement doit appartenir a la facture de l'URL : sinon la suppression
        // recalculerait le statut d'une facture qui n'est pas la sienne (integrite).
        abort_if($paiement->facture_id !== $facture->id, 404, 'Paiement introuvable pour cette facture.');

        // PaiementPolicy::delete : admin (tout) ou secretaire (ses propres saisies).
        $this->authorize('delete', $paiement);

        $this->facturationService->supprimerPaiement($paiement);

        return response()->json(['message' => 'Paiement supprimé.']);
    }
}
