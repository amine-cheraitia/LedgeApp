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
use Illuminate\Support\Facades\DB;

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

        return DB::transaction(function () use ($request, $facture) {
            // Verrou sur la facture : le controle du restant du et l'insertion sont
            // atomiques, ce qui evite un sur-credit en cas de paiements concurrents (TOCTOU).
            $facture = Facture::whereKey($facture->getKey())->lockForUpdate()->first();

            if ($facture->estSolde()) {
                return response()->json([
                    'message' => 'Cette facture est déjà soldée.',
                ], 409);
            }

            $montantRestant = $facture->montantRestant();
            if ((float) $request->validated('montant') > $montantRestant) {
                return response()->json([
                    'message' => "Le montant saisi dépasse le restant dû ({$montantRestant} DA).",
                    'errors' => ['montant' => ["Le montant ne peut pas dépasser {$montantRestant} DA."]],
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
        });
    }

    public function destroy(Facture $facture, Paiement $paiement): JsonResponse
    {
        // Le paiement doit appartenir a la facture de l'URL : sinon la suppression
        // recalculerait le statut d'une facture qui n'est pas la sienne (integrite).
        abort_if($paiement->facture_id !== $facture->id, 404, 'Paiement introuvable pour cette facture.');

        // PaiementPolicy::delete : admin (tout) ou secretaire (ses propres saisies).
        $this->authorize('delete', $paiement);

        $paiement->delete();
        $this->facturationService->recalculerStatutPaiement($facture);

        return response()->json(['message' => 'Paiement supprimé.']);
    }
}
