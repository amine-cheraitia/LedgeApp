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

class DevisController extends Controller
{
    public function __construct(
        private FacturationService $facturationService,
        private MissionService $missionService,
        private PdfService $pdfService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $devis = Devis::with('entreprise', 'prestation')
            ->when($request->entreprise_id, fn ($q, $id) => $q->where('entreprise_id', $id))
            ->when($request->statut, fn ($q, $s) => $q->where('statut', $s))
            ->when($request->search, fn ($q, $s) => $q->where('numero', 'like', "%{$s}%"))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return DevisResource::collection($devis);
    }

    public function store(StoreDevisRequest $request): JsonResponse
    {
        $devis = $this->facturationService->creerDevis(
            $request->validated(),
            $request->user()->id,
        );

        return (new DevisResource($devis->load('prestation', 'entreprise')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Devis $devis): DevisResource
    {
        return new DevisResource($devis->load('prestation', 'entreprise'));
    }

    public function update(UpdateDevisRequest $request, Devis $devis): DevisResource|JsonResponse
    {
        try {
            $devis = $this->facturationService->mettreAJourDevis($devis, $request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return new DevisResource($devis);
    }

    public function envoyer(Devis $devis): DevisResource|JsonResponse
    {
        try {
            $devis = $this->facturationService->envoyerDevis($devis);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return new DevisResource($devis);
    }

    public function accepter(Devis $devis): DevisResource|JsonResponse
    {
        try {
            $devis = $this->facturationService->accepterDevis($devis);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return new DevisResource($devis);
    }

    public function refuser(Devis $devis): DevisResource|JsonResponse
    {
        try {
            $devis = $this->facturationService->refuserDevis($devis);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return new DevisResource($devis);
    }

    public function convertirEnMission(ConvertirEnMissionRequest $request, Devis $devis): MissionResource|JsonResponse
    {
        try {
            $mission = $this->missionService->creerMission([
                'entreprise_id'     => $devis->entreprise_id,
                'prestation_id'     => $devis->prestation_id,
                'devis_id'          => $devis->id,
                'date_debut'        => $request->date_debut,
                'date_fin'          => $request->date_fin,
                'collaborateur_ids' => $request->collaborateur_ids ?? [],
                'notes'             => 'Genere depuis devis '.$devis->numero,
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return new MissionResource($mission);
    }

    public function pdf(Devis $devis): \Symfony\Component\HttpFoundation\Response
    {
        return $this->pdfService->genererDevis($devis)
            ->stream('devis-'.$devis->numero.'.pdf');
    }

    public function destroy(Devis $devis): JsonResponse
    {
        try {
            $this->facturationService->supprimerDevis($devis);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['message' => 'Devis supprime.']);
    }
}
