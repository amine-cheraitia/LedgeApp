<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exercices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exercices\StoreExerciceRequest;
use App\Http\Requests\Exercices\UpdateExerciceRequest;
use App\Http\Resources\Exercices\ExerciceResource;
use App\Models\Exercice;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExerciceController extends Controller
{
    public function __construct(private readonly PdfService $pdfService) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Exercice::class);

        $exercices = Exercice::latest('annee')->get();

        return ExerciceResource::collection($exercices);
    }

    public function store(StoreExerciceRequest $request): JsonResponse
    {
        $this->authorize('create', Exercice::class);

        $exercice = Exercice::create($request->validated());

        return (new ExerciceResource($exercice))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Exercice $exercice): ExerciceResource
    {
        $this->authorize('view', $exercice);

        return new ExerciceResource($exercice);
    }

    public function update(UpdateExerciceRequest $request, Exercice $exercice): ExerciceResource|JsonResponse
    {
        $this->authorize('update', $exercice);

        $validated = $request->validated();

        // On ne rouvre pas un exercice cloture qui porte deja des documents : cela
        // contournerait la separation stricte par annee (on pourrait y rattacher de
        // nouveaux documents apres coup).
        $reouverture = ($validated['statut'] ?? null) === 'ouvert' && $exercice->statut === 'cloture';
        if ($reouverture && ($exercice->missions()->exists() || $exercice->devis()->exists() || $exercice->factures()->exists())) {
            return response()->json([
                'message' => 'Impossible de rouvrir un exercice clôturé qui porte des missions, devis ou factures.',
            ], 409);
        }

        $exercice->update($validated);

        return new ExerciceResource($exercice);
    }

    public function destroy(Exercice $exercice): JsonResponse
    {
        $this->authorize('delete', $exercice);

        if ($exercice->missions()->exists() || $exercice->factures()->exists() || $exercice->devis()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un exercice ayant des missions, devis ou factures associes.',
            ], 409);
        }

        $exercice->delete();

        return response()->json(['message' => 'Exercice supprime.']);
    }

    public function current(): ExerciceResource|JsonResponse
    {
        $this->authorize('viewAny', Exercice::class);

        $exercice = Exercice::current();

        // Aucun exercice ouvert : reponse vide exploitable par le front (pas de 500).
        if ($exercice === null) {
            return response()->json(['data' => null]);
        }

        return new ExerciceResource($exercice);
    }

    public function rapportCloturePdf(Exercice $exercice): StreamedResponse
    {
        $this->authorize('view', $exercice);

        $pdf = $this->pdfService->genererRapportCloture($exercice);
        $filename = 'rapport-cloture-'.$exercice->annee.'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
