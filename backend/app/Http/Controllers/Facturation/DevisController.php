<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturation\StoreDevisRequest;
use App\Http\Resources\Facturation\DevisResource;
use App\Http\Resources\Planning\MissionResource;
use App\Models\Devis;
use App\Services\FacturationService;
use App\Services\MissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DevisController extends Controller
{
    public function __construct(
        private FacturationService $facturationService,
        private MissionService $missionService,
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

    public function update(Request $request, Devis $devis): DevisResource|JsonResponse
    {
        if ($devis->statut !== 'brouillon') {
            return response()->json([
                'message' => 'Seuls les devis en brouillon peuvent etre modifies.',
            ], 409);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'date_validite' => ['sometimes', 'date'],
        ]);

        $devis->update($validated);

        return new DevisResource($devis->load('prestation', 'entreprise'));
    }

    /**
     * Marque un devis brouillon comme envoye.
     */
    public function envoyer(Devis $devis): DevisResource|JsonResponse
    {
        if ($devis->statut !== 'brouillon') {
            return response()->json([
                'message' => 'Seuls les devis en brouillon peuvent etre envoyes.',
            ], 409);
        }

        $devis->update(['statut' => 'envoye']);

        return new DevisResource($devis->load('prestation', 'entreprise'));
    }

    /**
     * Marque un devis envoye comme accepte.
     */
    public function accepter(Devis $devis): DevisResource|JsonResponse
    {
        if ($devis->statut !== 'envoye') {
            return response()->json([
                'message' => 'Seuls les devis envoyes peuvent etre acceptes.',
            ], 409);
        }

        $devis->update(['statut' => 'accepte']);

        return new DevisResource($devis->load('prestation', 'entreprise'));
    }

    /**
     * Marque un devis envoye comme refuse.
     */
    public function refuser(Devis $devis): DevisResource|JsonResponse
    {
        if ($devis->statut !== 'envoye') {
            return response()->json([
                'message' => 'Seuls les devis envoyes peuvent etre refuses.',
            ], 409);
        }

        $devis->update(['statut' => 'refuse']);

        return new DevisResource($devis->load('prestation', 'entreprise'));
    }

    /**
     * Convertit un devis accepte en mission.
     * La prestation et l'entreprise viennent du devis — pas de resaisie.
     */
    public function convertirEnMission(Request $request, Devis $devis): MissionResource|JsonResponse
    {
        if ($devis->statut !== 'accepte') {
            return response()->json([
                'message' => 'Seuls les devis acceptes peuvent etre convertis en mission.',
            ], 409);
        }

        $validated = $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'collaborateur_ids' => ['nullable', 'array'],
            'collaborateur_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $mission = $this->missionService->creerMission([
            'entreprise_id' => $devis->entreprise_id,
            'prestation_id' => $devis->prestation_id,
            'devis_id' => $devis->id,
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'] ?? null,
            'collaborateur_ids' => $validated['collaborateur_ids'] ?? [],
            'notes' => 'Genere depuis devis '.$devis->numero,
        ]);

        return new MissionResource($mission);
    }

    public function destroy(Devis $devis): JsonResponse
    {
        if ($devis->statut !== 'brouillon') {
            return response()->json([
                'message' => 'Seuls les devis en brouillon peuvent etre supprimes.',
            ], 409);
        }

        $devis->delete();

        return response()->json(['message' => 'Devis supprime.']);
    }
}
