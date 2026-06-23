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
use Illuminate\Http\Request;

class PaiementController extends Controller
{
    public function __construct(private FacturationService $facturationService) {}

    public function index(Facture $facture): AnonymousResourceCollection
    {
        return PaiementResource::collection(
            $facture->paiements()->with('recordedBy')->latest('date_paiement')->get()
        );
    }

    public function store(StorePaiementRequest $request, Facture $facture): JsonResponse
    {
        if ($facture->estSolde()) {
            return response()->json([
                'message' => 'Cette facture est déjà soldée.',
            ], 409);
        }

        $montantRestant = $facture->montantRestant();
        if ((float) $request->validated('montant') > $montantRestant) {
            return response()->json([
                'message' => "Le montant saisi dépasse le restant dû ({$montantRestant} DA).",
                'errors'  => ['montant' => ["Le montant ne peut pas dépasser {$montantRestant} DA."]],
            ], 422);
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

        // Mettre a jour le mode de paiement sur la facture
        $facture->update(['mode_paiement' => $request->validated('mode_paiement')]);

        $this->facturationService->recalculerStatutPaiement($facture);

        if ($facture->estSolde()) {
            InvoicePaid::dispatch($facture);
        }

        return (new PaiementResource($paiement))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Facture $facture, Paiement $paiement): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('admin') && $paiement->recorded_by !== $user->id) {
            return response()->json(['message' => 'Vous ne pouvez supprimer que vos propres saisies.'], 403);
        }

        $paiement->delete();
        $this->facturationService->recalculerStatutPaiement($facture);

        return response()->json(['message' => 'Paiement supprimé.']);
    }
}
