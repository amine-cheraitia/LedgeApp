<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturation\ConvertirEnMissionRequest;
use App\Http\Requests\Facturation\StoreDevisRequest;
use App\Http\Requests\Facturation\UpdateDevisRequest;
use App\Http\Resources\Facturation\DevisResource;
use App\Http\Resources\Planning\MissionResource;
use App\Models\Devis;
use App\Services\FacturationService;
use App\Services\MissionService;
use App\Services\PdfService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class DevisController extends Controller
{
    public function __construct(
        private readonly FacturationService $facturationService,
        private readonly MissionService $missionService,
        private readonly PdfService $pdfService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Devis::class);

        $devis = $this->facturationService->listerDevis($request->only([
            'entreprise_id', 'exercice_id', 'statut', 'search', 'sort_field', 'sort_direction', 'per_page',
        ]));

        return DevisResource::collection($devis);
    }

    public function store(StoreDevisRequest $request): JsonResponse
    {
        $this->authorize('create', Devis::class);

        try {
            $devis = $this->facturationService->creerDevis(
                $request->validated(),
                $request->user()->id,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new DevisResource($devis->load('prestation', 'entreprise')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Devis $devis): DevisResource
    {
        $this->authorize('view', $devis);

        return new DevisResource($devis->load('prestation', 'entreprise'));
    }

    public function update(UpdateDevisRequest $request, Devis $devis): DevisResource|JsonResponse
    {
        $this->authorize('update', $devis);

        try {
            $devis = $this->facturationService->mettreAJourDevis($devis, $request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return new DevisResource($devis);
    }

    public function envoyer(Devis $devis): DevisResource|JsonResponse
    {
        $this->authorize('envoyer', $devis);

        try {
            $devis = $this->facturationService->envoyerDevis($devis);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return new DevisResource($devis);
    }

    public function accepter(Devis $devis): DevisResource|JsonResponse
    {
        $this->authorize('update', $devis);

        try {
            $devis = $this->facturationService->accepterDevis($devis);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return new DevisResource($devis);
    }

    public function refuser(Devis $devis): DevisResource|JsonResponse
    {
        $this->authorize('update', $devis);

        try {
            $devis = $this->facturationService->refuserDevis($devis);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return new DevisResource($devis);
    }

    public function convertirEnMission(ConvertirEnMissionRequest $request, Devis $devis): MissionResource|JsonResponse
    {
        $this->authorize('update', $devis);

        try {
            $mission = $this->missionService->creerMission([
                'entreprise_id' => $devis->entreprise_id,
                'prestation_id' => $devis->prestation_id,
                'devis_id' => $devis->id,
                'date_debut' => $request->date_debut,
                'date_fin' => $request->date_fin,
                'collaborateur_ids' => $request->collaborateur_ids ?? [],
                'notes' => 'Genere depuis devis '.$devis->numero,
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return new MissionResource($mission);
    }

    public function pdf(Devis $devis): Response
    {
        $this->authorize('view', $devis);

        return $this->pdfService->genererDevis($devis)
            ->stream('devis-'.$devis->numero.'.pdf');
    }

    public function destroy(Devis $devis): JsonResponse
    {
        $this->authorize('delete', $devis);

        try {
            $this->facturationService->supprimerDevis($devis);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['message' => 'Devis supprime.']);
    }
}
